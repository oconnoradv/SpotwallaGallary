"""Build a WordPress-installable ZIP containing only shipped plugin files."""

import argparse
import hashlib
import re
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo


ROOT = Path(__file__).resolve().parents[2]
PLUGIN = "gallery-for-spotwalla"
FILES = (
    "gallery-for-spotwalla.php",
    "readme.txt",
    "README.md",
    "LICENSE",
    "languages/gallery-for-spotwalla.pot",
)


def build(tag: str = "") -> Path:
    source = (ROOT / FILES[0]).read_text(encoding="utf-8")
    match = re.search(r"^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)\s*$", source, re.MULTILINE)
    if not match:
        raise ValueError("Plugin header must contain a stable major.minor.patch version.")
    version = match.group(1)
    if tag and tag != f"v{version}":
        raise ValueError(f"Release tag {tag!r} must match plugin version v{version}.")

    output = ROOT / "dist"
    output.mkdir(exist_ok=True)
    archive = output / f"{PLUGIN}-{version}.zip"
    expected = {f"{PLUGIN}/{name}" for name in FILES}
    with ZipFile(archive, "w", compression=ZIP_DEFLATED) as package:
        for name in FILES:
            info = ZipInfo(f"{PLUGIN}/{name}", date_time=(1980, 1, 1, 0, 0, 0))
            info.compress_type = ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            package.writestr(info, (ROOT / name).read_bytes())

    with ZipFile(archive) as package:
        if set(package.namelist()) != expected or package.testzip() is not None:
            raise ValueError("ZIP contents or integrity verification failed.")
        for name in FILES:
            if package.read(f"{PLUGIN}/{name}") != (ROOT / name).read_bytes():
                raise ValueError(f"ZIP content differs from source: {name}")

    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    archive.with_suffix(".zip.sha256").write_text(
        f"{digest}  {archive.name}\n", encoding="ascii"
    )
    print(f"Built and verified {archive.name}")
    return archive


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--tag", default="", help="Require a matching vX.Y.Z release tag.")
    build(parser.parse_args().tag)
