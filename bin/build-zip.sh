#!/usr/bin/env bash
# Maakt dist/hashtagmedia-toolkit-{versie}.zip om in WordPress te uploaden.
# Laat ontwikkelbestanden weg (zie .gitattributes export-ignore).
# Oude zips worden eerst opgeruimd; elke versie staat in git als tag (vX.Y.Z).
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(sed -n 's/^ \* Version: *//p' hashtagmedia-toolkit.php | tr -d '[:space:]')
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
rm -rf dist
mkdir -p "$TMP/hashtagmedia-toolkit" dist
rsync -a \
	--exclude '.git' --exclude '.github' --exclude '.gitattributes' --exclude '.gitignore' \
	--exclude 'composer.json' --exclude 'composer.lock' --exclude 'vendor' \
	--exclude 'phpcs.xml.dist' --exclude 'README.md' --exclude 'PLAN.md' \
	--exclude 'bin' --exclude 'dist' --exclude '.gitkeep' --exclude '.DS_Store' \
	./ "$TMP/hashtagmedia-toolkit/"
( cd "$TMP" && zip -rqX plugin.zip hashtagmedia-toolkit )
cp -f "$TMP/plugin.zip" "dist/hashtagmedia-toolkit-$VERSION.zip"
echo "dist/hashtagmedia-toolkit-$VERSION.zip"
