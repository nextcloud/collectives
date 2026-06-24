#!/bin/sh
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Downloads a portable Hugo binary into ssg/.runtime so that the PHP backend can
# run the Hugo static site generator at runtime, even when the Nextcloud
# container/host has no system-wide Hugo installed.
#
# Hugo ships as a single static Go binary, so no further dependencies are needed.
# The binary is placed inside the (bind-mounted) app directory on purpose: it then
# works both on the host and inside the Nextcloud container as long as they share
# the same OS/architecture (linux-amd64 in the dev setup).
set -eu

HUGO_VERSION="${HUGO_VERSION:-0.140.2}"
ARCH="${HUGO_ARCH:-linux-amd64}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
RUNTIME_DIR="$SCRIPT_DIR/.runtime"
TARGET="$RUNTIME_DIR/hugo"

if [ -x "$TARGET" ]; then
	echo "Hugo runtime already present: $("$TARGET" version)"
	exit 0
fi

TARBALL="hugo_${HUGO_VERSION}_${ARCH}.tar.gz"
URL="https://github.com/gohugoio/hugo/releases/download/v${HUGO_VERSION}/${TARBALL}"

echo "Downloading $URL"
mkdir -p "$RUNTIME_DIR"
curl -fsSL "$URL" -o "$RUNTIME_DIR/$TARBALL"
tar -xzf "$RUNTIME_DIR/$TARBALL" -C "$RUNTIME_DIR" hugo
rm -f "$RUNTIME_DIR/$TARBALL"
chmod +x "$TARGET"

echo "Installed portable Hugo runtime: $("$TARGET" version)"
