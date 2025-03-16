#!/bin/bash
# SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

export CYPRESS_baseUrl="${CYPRESS_baseUrl:-http://localhost:8081/index.php}"
export CYPRESS_ncVersion="${CYPRESS_ncVersion:-master}"
export APP_SOURCE="$PWD/.."
export LANG="en_EN.UTF-8"

function finish {
	docker compose down
}
trap finish EXIT

if ! npm exec wait-on >/dev/null; then
	npm install --no-save wait-on
fi

# start server if it's not running yet
if npm exec wait-on -- -i 500 -t 2000 "$CYPRESS_baseUrl" 2>/dev/null; then
	echo Server is up at "$CYPRESS_baseUrl"
else
	echo No server reached at "$CYPRESS_baseUrl" - starting containers.
	DOCKER_BUILDKIT=1 docker compose up -d
	if ! npm exec wait-on -- -i 500 -t 240000 "$CYPRESS_baseUrl" 2>/dev/null; then
		echo Waiting for "$CYPRESS_baseUrl" timed out.
		echo Container logs:
		docker compose logs
		exit 1
	fi
fi

(cd .. && npm exec cypress -- "$@")
