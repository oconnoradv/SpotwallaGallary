import hashlib
import importlib.util
import re
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
from zipfile import ZipFile


SPEC = importlib.util.spec_from_file_location(
    "build_release", Path(__file__).resolve().parents[1] / "scripts" / "build_release.py"
)
release = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(release)


class BuildReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        for name in release.FILES:
            content = "<?php\n/**\n * Version: 1.2.3\n */\n" if name.endswith(".php") else name
            (self.root / name).parent.mkdir(parents=True, exist_ok=True)
            (self.root / name).write_text(content, encoding="utf-8")
        self.patch = patch.object(release, "ROOT", self.root)
        self.patch.start()
        self.addCleanup(self.patch.stop)

    def test_archive_allowlist_content_checksum_and_reproducibility(self):
        (self.root / "secret.txt").write_text("not shipped", encoding="utf-8")
        archive = release.build("v1.2.3")
        original = archive.read_bytes()
        with ZipFile(archive) as package:
            self.assertEqual(
                sorted(package.namelist()),
                sorted(f"{release.PLUGIN}/{name}" for name in release.FILES),
            )
            for name in release.FILES:
                self.assertEqual(package.read(f"{release.PLUGIN}/{name}"), (self.root / name).read_bytes())
        self.assertEqual(
            archive.with_suffix(".zip.sha256").read_text(encoding="ascii"),
            f"{hashlib.sha256(original).hexdigest()}  {archive.name}\n",
        )
        self.assertEqual(release.build().read_bytes(), original)

    def test_mismatching_or_invalid_tags_do_not_build(self):
        for tag in ("v9.9.9", "1.2.3", "v1.2.3-rc1"):
            with self.subTest(tag=tag), self.assertRaises(ValueError):
                release.build(tag)
        self.assertFalse((self.root / "dist").exists())

    def test_missing_version_or_missing_file_fails(self):
        (self.root / release.FILES[0]).write_text("<?php\n", encoding="utf-8")
        with self.assertRaises(ValueError):
            release.build()
        (self.root / release.FILES[0]).write_text(" * Version: 1.2.3\n", encoding="utf-8")
        (self.root / "LICENSE").unlink()
        with self.assertRaises(FileNotFoundError):
            release.build()

    def test_every_runtime_php_file_is_explicitly_packaged(self):
        repo = Path(__file__).resolve().parents[2]
        runtime = {
            path.relative_to(repo).as_posix()
            for path in (repo / "includes").glob("*.php")
        }
        self.assertTrue(runtime)
        self.assertEqual(
            runtime, {name for name in release.FILES if name.startswith("includes/")}
        )
        source = (repo / release.FILES[0]).read_text(encoding="utf-8")
        required = set(re.findall(r"require_once __DIR__ \. '/([^']+)';", source))
        self.assertEqual(runtime, required)


if __name__ == "__main__":
    unittest.main()
