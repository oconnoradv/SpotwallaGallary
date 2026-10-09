"""Fail closed unless Dependabot and exact-commit CodeQL checks approve a release."""

import json
import os
import subprocess
import time


class GateError(RuntimeError):
    """Release security prerequisites are missing, failed, or contain findings."""


def api(path, token, paginate=False):
    """Read GitHub JSON without exposing credentials in command arguments or logs."""
    env = dict(os.environ, GH_TOKEN=token)
    command = ["gh", "api", path]
    if paginate:
        command.extend(["--paginate", "--slurp"])
    try:
        result = subprocess.run(
            command, env=env, capture_output=True, text=True, check=False, timeout=60
        )
    except (OSError, subprocess.TimeoutExpired) as error:
        raise GateError("GitHub security API could not be reached; publication is blocked.") from error
    if result.returncode:
        raise GateError(
            "GitHub security API request failed. Check token permissions, repository "
            "security settings, and GitHub availability; publication is blocked."
        )
    try:
        value = json.loads(result.stdout)
    except json.JSONDecodeError as error:
        raise GateError("GitHub returned invalid JSON; publication is blocked.") from error
    if paginate:
        if not isinstance(value, list) or any(not isinstance(page, list) for page in value):
            raise GateError("Unexpected paginated security response; publication is blocked.")
        return [item for page in value for item in page]
    return value


def check_dependabot(repo, token):
    alerts = api(
        f"repos/{repo}/dependabot/alerts?state=open&per_page=100", token, paginate=True
    )
    if alerts:
        raise GateError(
            f"Dependabot reports {len(alerts)} open alert(s). Resolve or explicitly "
            "dismiss them in GitHub before publishing. All severities block publication."
        )
    print("Dependabot: no open alerts.")


def check_codeql(repo, sha, token):
    setup = api(f"repos/{repo}/code-scanning/default-setup", token)
    if (
        not isinstance(setup, dict)
        or setup.get("state") != "configured"
        or not setup.get("languages")
    ):
        raise GateError("CodeQL default setup must be enabled with language coverage.")
    categories = {f"/language:{language}" for language in setup["languages"]}
    analyses = api(
        f"repos/{repo}/code-scanning/analyses"
        "?ref=refs%2Fheads%2Fmain&tool_name=CodeQL&per_page=100",
        token,
        paginate=True,
    )
    # The API lists newest analyses first. Never approve using another commit,
    # another tool, a partial language set, or an older clean rerun.
    latest = {}
    for analysis in analyses:
        if (
            analysis.get("commit_sha") == sha
            and analysis.get("ref") == "refs/heads/main"
            and analysis.get("tool", {}).get("name") == "CodeQL"
            and analysis.get("category") in categories
        ):
            latest.setdefault(analysis["category"], analysis)
    for analysis in latest.values():
        if analysis.get("error"):
            raise GateError("CodeQL analysis failed for the release commit.")
        count = analysis.get("results_count")
        if not isinstance(count, int) or isinstance(count, bool) or count < 0:
            raise GateError("CodeQL analysis has no valid result count.")
        if count:
            raise GateError(
                f"CodeQL reports {count} finding(s) for the release commit. "
                "Publication requires a clean analysis in every configured language."
            )
    return categories.issubset(latest)


def gate(repo, sha, alerts_token, codeql_token, attempts=30, delay=30):
    if not alerts_token:
        raise GateError(
            "Missing RELEASE_SECURITY_TOKEN repository secret. Configure a token "
            "with Dependabot alerts: read permission for this repository."
        )
    if not codeql_token or not repo or not sha:
        raise GateError("Missing repository, commit, or CodeQL API credentials.")
    check_dependabot(repo, alerts_token)
    for attempt in range(attempts):
        if check_codeql(repo, sha, codeql_token):
            # Refresh alerts after waiting, rather than approving a stale snapshot.
            check_dependabot(repo, alerts_token)
            print(f"CodeQL: clean analysis for release commit {sha}.")
            return
        if attempt + 1 < attempts:
            print("Waiting for CodeQL default setup to analyze the release commit on main.")
            time.sleep(delay)
    raise GateError(
        "No complete CodeQL analysis for the exact release commit within 15 minutes. "
        "Merge to main, wait for CodeQL, then rerun the failed release workflow."
    )


if __name__ == "__main__":
    try:
        gate(
            os.environ.get("GITHUB_REPOSITORY", ""),
            os.environ.get("GITHUB_SHA", ""),
            os.environ.get("RELEASE_SECURITY_TOKEN", ""),
            os.environ.get("GH_TOKEN", ""),
        )
    except GateError as error:
        raise SystemExit(f"Release security gate failed: {error}") from error
