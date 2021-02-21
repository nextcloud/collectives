#!/bin/bash
git clone -b stable20 --depth 1 \
	https://github.com/nextcloud/viewer /var/www/html/apps/viewer
git clone -b stable20 --depth 1 \
	https://github.com/nextcloud/text /var/www/html/apps/text
su www-data -c "
php occ config:system:set force_language --value en
php /var/www/html/occ app:enable viewer
php /var/www/html/occ app:enable text
php /var/www/html/occ app:enable circles
php /var/www/html/occ app:enable collectives
php /var/www/html/occ app:list
OC_PASS=bob php /var/www/html/occ user:add --password-from-env \
	--group='Bobs Group' bob
OC_PASS=jane php /var/www/html/occ user:add --password-from-env \
	--group='Bobs Group' jane
php /var/www/html/occ config:app:set circles --value 1 allow_linked_groups
php /var/www/html/occ config:system:set --type bool --value true -- allow_local_remote_servers
php /var/www/html/occ config:app:set --value 1 -- circles self_signed_cert
php /var/www/html/occ config:app:set --value 1 -- circles allow_non_ssl_links
php /var/www/html/occ config:app:set --value 1 -- circles local_is_non_ssl
"
