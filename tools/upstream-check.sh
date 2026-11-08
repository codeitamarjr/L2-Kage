#!/usr/bin/env bash
# Read-only report on what an L2J Mobius update would change, before merging it. See docs/UPSTREAM-UPDATE.md.
#   tools/upstream-check.sh            fetches upstream, compares with upstream/master
#   tools/upstream-check.sh <commit>   compares with that upstream commit instead
# Changes nothing: no merge, no checkout, no files written.
set -euo pipefail
cd "$(dirname "$0")/.."

C=L2J_Mobius_Essence_10.1_SamuraiCrow
target=${1:-upstream/master}
[ $# -eq 0 ] && git fetch -q upstream

base=$(git merge-base HEAD "$target")
echo "Base (upstream commit we last merged): $(git log -1 --format='%h %ad %s' --date=short "$base")"
echo "Target: $(git log -1 --format='%h %ad %s' --date=short "$target")"
echo

echo "== Essence commits to take in =="
git log --format='%h %ad %s' --date=short "$base..$target" -- "$C"
echo

# An override shadows the upstream file. If upstream fixed that file, the fix is lost unless we redo it in ours.
echo "== Files we override that upstream changed (port these changes by hand) =="
found=0
for dir in game login; do
	[ -d "overrides/$dir" ] || continue
	while IFS= read -r f; do
		if ! git diff --quiet "$base" "$target" -- "$C/dist/$dir/$f"; then
			echo "  overrides/$dir/$f"
			echo "      git diff $base $target -- $C/dist/$dir/$f"
			found=1
		fi
	done < <(cd "overrides/$dir" && find . -type f | sed 's|^\./||' | sort)
done
[ $found -eq 0 ] && echo "  none"
echo

# New tables or columns are NOT applied to an existing database: db-init only runs on an empty data folder.
echo "== Database scripts (apply by hand with ALTER/CREATE; never re-run a whole file, they DROP tables) =="
git diff --name-status "$base" "$target" -- "$C/dist/db_installer" | sed 's/^/  /' | grep . || echo "  none"
echo

echo "== Config files (new keys use their default unless we override the file) =="
git diff --name-status "$base" "$target" -- "$C/dist/game/config" "$C/dist/login/config" | sed 's/^/  /' | grep . || echo "  none"
echo

echo "== Seasonal events (the website reads these) =="
git diff --name-status "$base" "$target" -- "$C/dist/game/data/scripts/events" | sed 's/^/  /' | grep . || echo "  none"
echo

echo "== Login server code (if none, only the game container needs recreating) =="
git diff --name-status "$base" "$target" -- "$C/java/org/l2jmobius/loginserver" | sed 's/^/  /' | grep . || echo "  none"
echo

# Our Java overrides compile against GameServer.jar at startup; a changed class they import may break them.
echo "== Changed classes our Java overrides import (compile-check after building) =="
changed=$(git diff --name-only "$base" "$target" -- "$C/java" | sed 's|.*/||; s|\.java$||' | sort -u)
imports=$(grep -ho 'import [A-Za-z0-9_.]*;' $(find overrides -name '*.java') | sed 's/.*\.//; s/;//' | sort -u)
comm -12 <(echo "$imports") <(echo "$changed") | sed 's/^/  /' | grep . || echo "  none"
echo

echo "== Dry-run merge =="
if out=$(git merge-tree --write-tree --name-only HEAD "$target" 2>&1); then
	echo "  merges cleanly"
else
	echo "  CONFLICTS:"
	echo "$out" | sed -n '2,/^$/p' | sed 's/^/    /'
fi
