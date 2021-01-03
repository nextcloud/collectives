#!/bin/bash
git clone -b stable20 https://github.com/nextcloud/viewer /var/www/html/apps/viewer
git clone -b stable20 https://github.com/nextcloud/text /var/www/html/apps/text
su www-data -c "
php occ config:system:set force_language --value en
php /var/www/html/occ app:enable viewer
php /var/www/html/occ app:enable text
php /var/www/html/occ app:enable circles
php /var/www/html/occ app:enable collectives
php /var/www/html/occ app:list
"
