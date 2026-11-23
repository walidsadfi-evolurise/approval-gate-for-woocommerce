#!/usr/bin/env bash
# Publish one approved plugin from GitHub to its WordPress.org SVN repository.
#
# Usage:  ./publish-to-wporg.sh evolurise-approval-gate-for-woocommerce
#         (the GitHub repository is the slug without "evolurise-"; pass it as 2nd argument if different)
#
# Run it only after WordPress.org has emailed you that the plugin is approved
# (before that the SVN repository does not exist). Requires git, svn and rsync.
# SVN asks for your password itself: it is never stored in this script.

set -euo pipefail

SLUG="${1:?Usage: $0 <plugin-slug> [github-repo]}"
REPO="${2:-${SLUG#evolurise-}}"
SVN_USER="evolurise"
GITHUB="https://github.com/walidsadfi-evolurise/${REPO}.git"
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"
WORK="$(mktemp -d)"
trap 'rm -rf "${WORK:?}"' EXIT

echo "→ Fetching ${SLUG} from GitHub"
git clone --quiet --depth 1 "$GITHUB" "$WORK/git"

VERSION="$(grep -m1 -i '^Stable tag:' "$WORK/git/readme.txt" | awk '{print $3}')"
HEADER_VERSION="$(grep -m1 -i 'Version:' "$WORK/git/${SLUG}.php" | awk '{print $NF}')"
if [ -z "$VERSION" ] || [ "$VERSION" != "$HEADER_VERSION" ]; then
  echo "✗ Stable tag ($VERSION) and plugin header Version ($HEADER_VERSION) differ — fix that first." >&2
  exit 1
fi
echo "→ Version ${VERSION}"

echo "→ Checking out ${SVN_URL}"
svn checkout --quiet --depth immediates "$SVN_URL" "$WORK/svn"
svn update --quiet --set-depth infinity "$WORK/svn/trunk" "$WORK/svn/assets"

if svn ls "${SVN_URL}/tags/${VERSION}" >/dev/null 2>&1; then
  echo "✗ tags/${VERSION} already exists on WordPress.org. Bump the version before publishing again." >&2
  exit 1
fi

echo "→ Copying the distributable files into trunk/ (no tests, CI or dev tooling)"
rsync -a --delete \
  --exclude=.git --exclude=.github --exclude=.gitignore --exclude=.phpcs.xml.dist \
  --exclude=bin --exclude=tests --exclude='phpunit*.xml.dist' \
  --exclude=composer.json --exclude=composer.lock --exclude=vendor --exclude=node_modules \
  --exclude=README.md --exclude=.wordpress-org \
  "$WORK/git/" "$WORK/svn/trunk/"

cd "$WORK/svn"
svn add --force --quiet trunk
# Files removed from the plugin since the last release.
svn status trunk | awk '/^!/ {print $2}' | while read -r gone; do svn delete --quiet "$gone"; done

svn copy --quiet trunk "tags/${VERSION}"

# Directory page assets (icon, banner) kept in the repo's .wordpress-org/ folder.
if [ -d "$WORK/git/.wordpress-org" ]; then
  echo "→ Updating the directory icon and banner"
  rsync -a "$WORK/git/.wordpress-org/" "$WORK/svn/assets/"
  svn add --force --quiet assets
  for img in "$WORK"/svn/assets/*.png; do svn propset --quiet svn:mime-type image/png "$img"; done
fi

echo
svn status
echo
read -r -p "Commit ${SLUG} ${VERSION} to WordPress.org as '${SVN_USER}'? [y/N] " ok
[ "$ok" = "y" ] || { echo "Nothing committed."; exit 0; }

svn commit --username "$SVN_USER" -m "Release ${VERSION}"
echo "✓ Published: https://wordpress.org/plugins/${SLUG}/ (the page can take a few minutes to update)"
