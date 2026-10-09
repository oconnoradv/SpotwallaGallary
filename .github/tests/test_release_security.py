import importlib.util
import unittest
from pathlib import Path
from unittest.mock import patch


SPEC = importlib.util.spec_from_file_location(
    "check_release_security",
    Path(__file__).resolve().parents[1] / "scripts" / "check_release_security.py",
)
security = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(security)


def analysis(sha="release-sha", count=0, error="", category="/language:actions"):
    return {
        "commit_sha": sha,
        "ref": "refs/heads/main",
        "tool": {"name": "CodeQL"},
        "category": category,
        "results_count": count,
        "error": error,
    }


class ReleaseSecurityTests(unittest.TestCase):
    def test_missing_alert_token_blocks_before_api_access(self):
        with patch.object(security, "api") as api:
            with self.assertRaisesRegex(security.GateError, "RELEASE_SECURITY_TOKEN"):
                security.gate("owner/repo", "release-sha", "", "codeql")
            api.assert_not_called()

    def test_dependabot_blocks_any_open_alert(self):
        with patch.object(security, "api", return_value=[{"number": 1}]):
            with self.assertRaisesRegex(security.GateError, "open alert"):
                security.check_dependabot("owner/repo", "alerts")

    def test_exact_commit_clean_analysis_is_required(self):
        setup = {"state": "configured", "languages": ["actions"]}
        for analyses, expected in (
            ([analysis()], True),
            ([analysis(sha="other-sha")], False),
            ([], False),
            ([analysis(category="/language:python")], False),
        ):
            with self.subTest(analyses=analyses):
                with patch.object(security, "api", return_value=analyses):
                    self.assertEqual(
                        security.check_codeql("owner/repo", "release-sha", "codeql"),
                        expected,
                    )

    def test_failed_findings_and_invalid_results_block(self):
        setup = {"state": "configured", "languages": ["actions"]}
        for result in (analysis(count=1), analysis(error="failed"), analysis(count=None)):
            with self.subTest(result=result):
                # An older clean rerun must not conceal the latest failed one.
                with patch.object(security, "api", return_value=[result, analysis()]):
                    with self.assertRaises(security.GateError):
                        security.check_codeql("owner/repo", "release-sha", "codeql")

    def test_all_configured_languages_are_required(self):
        setup = {"state": "configured", "languages": ["actions", "python"]}
        with patch.object(security, "CODEQL_CATEGORIES", {"/language:actions", "/language:python"}):
            with patch.object(security, "api", return_value=[analysis()]):
                self.assertFalse(security.check_codeql("owner/repo", "release-sha", "codeql"))
            with patch.object(
                security, "api",
                return_value=[analysis(), analysis(category="/language:python")],
            ):
                self.assertTrue(security.check_codeql("owner/repo", "release-sha", "codeql"))

    def test_disabled_codeql_blocks(self):
        with patch.object(security, "api", return_value=[]):
            self.assertFalse(security.check_codeql("owner/repo", "release-sha", "codeql"))

    def test_timeout_blocks_and_success_refreshes_alerts(self):
        with patch.object(security, "check_dependabot") as alerts:
            with patch.object(security, "check_codeql", return_value=False):
                with self.assertRaisesRegex(security.GateError, "No complete CodeQL"):
                    security.gate("owner/repo", "sha", "alerts", "codeql", attempts=1)
            self.assertEqual(alerts.call_count, 1)
        with patch.object(security, "check_dependabot") as alerts:
            with patch.object(security, "check_codeql", return_value=True):
                security.gate("owner/repo", "sha", "alerts", "codeql", attempts=1)
            self.assertEqual(alerts.call_count, 2)
        with patch.object(security, "check_dependabot"):
            with patch.object(security, "check_codeql", side_effect=[False, True]):
                with patch.object(security.time, "sleep") as sleep:
                    security.gate("owner/repo", "sha", "alerts", "codeql", attempts=2)
                    sleep.assert_called_once_with(30)

    def test_api_errors_fail_closed_without_printing_secrets(self):
        result = type("Result", (), {"returncode": 1, "stderr": "token", "stdout": ""})()
        with patch.object(security.subprocess, "run", return_value=result):
            with self.assertRaisesRegex(security.GateError, "request failed"):
                security.api("repos/owner/repo/dependabot/alerts", "private-token")
        result.returncode = 0
        result.stdout = "invalid"
        with patch.object(security.subprocess, "run", return_value=result):
            with self.assertRaisesRegex(security.GateError, "invalid JSON"):
                security.api("path", "private-token")
        with patch.object(
            security.subprocess, "run",
            side_effect=security.subprocess.TimeoutExpired("gh", 60),
        ):
            with self.assertRaisesRegex(security.GateError, "could not be reached"):
                security.api("path", "private-token")

    def test_pagination_includes_every_alert(self):
        result = type("Result", (), {"returncode": 0, "stdout": '[[], [{"number": 101}]]'})()
        with patch.object(security.subprocess, "run", return_value=result):
            with self.assertRaisesRegex(security.GateError, "open alert"):
                security.check_dependabot("owner/repo", "alerts")


if __name__ == "__main__":
    unittest.main()
