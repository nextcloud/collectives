#!/bin/bash

export CYPRESS_baseUrl=${CYPRESS_baseUrl:-http://localhost:8081/index.php}
# Retrieve nextcloud version from used docker-compose image
export CYPRESS_ncVersion="$(docker-compose config 2>/dev/null| sed -ne 's/^\s*image: .*:php[0-9]\+\.[0-9]\+-nc//p')"
export APP_SOURCE=$PWD/..
export LANG="en_EN.UTF-8"

function finish {
	docker-compose down
}
trap finish EXIT

if ! npm exec wait-on >/dev/null; then
	npm install --no-save wait-on
fi

# start server if it's not running yet
if npm exec wait-on -- -i 500 -t 1000 "$CYPRESS_baseUrl" 2>/dev/null; then
	echo Server is up at "$CYPRESS_baseUrl"
else
	echo No server reached at "$CYPRESS_baseUrl" - starting containers.
	DOCKER_BUILDKIT=1 docker-compose up -d
	if ! npm exec wait-on -- -i 500 -t 240000 "$CYPRESS_baseUrl" 2>/dev/null; then
		echo Waiting for "$CYPRESS_baseUrl" timed out.
		echo Container logs:
		docker-compose logs
		exit 1
	fi
fi

(cd .. && npm exec cypress -- "$@")
