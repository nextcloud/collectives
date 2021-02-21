#!/bin/bash

export CYPRESS_baseUrl=http://localhost:8081/index.php
export APP_SOURCE=$PWD/..

function finish {
	docker-compose down
}
trap finish EXIT

docker-compose up -d --no-recreate

npm install --no-save wait-on
echo "starting to wait for server $CYPRESS_baseUrl"
$(npm bin)/wait-on -i 500 -t 300000 $CYPRESS_baseUrl || (docker-compose logs && exit 1)
docker-compose exec -T nextcloud bash /var/www/html/apps/collectives/cypress/server.sh

(cd .. && $(npm bin)/cypress $@)


