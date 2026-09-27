#!/usr/bin/env bash
# Before/after schema check in one command:
#   compare-with-ref.sh [git-ref]   (default HEAD — i.e. compare uncommitted work with the last commit)
# Renders all page types for <ref> and for the working tree, then prints the
# Google-view diff (compare.py), graph integrity and new PHP notices.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; ref="${1:-HEAD}"
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"
git -C "$repo" worktree remove --force "$base/ref" >/dev/null 2>&1 || true
git -C "$repo" worktree add --detach -f "$base/ref" "$ref" >/dev/null
"$here/run.sh" "$base/ref" "$base/out-ref"
"$here/run.sh" "$repo" "$base/out-work"
git -C "$repo" worktree remove --force "$base/ref"
echo; echo "=== JSON-LD: $ref → working tree (Google view: same-@id nodes = one entity)"
python3 "$here/compare.py" "$base/out-ref" "$base/out-work"
echo; echo "=== Integrity (working tree)"; python3 "$here/integrity.py" "$base/out-work"
echo; echo "=== PHP notices (working tree)"
grep -h "PHP " "$base/out-work/debug.log" 2>/dev/null | sed 's/^\[[^]]*\] //' | grep -v "comments.php" | cut -c1-220 | sort | uniq -c || echo "  none"
