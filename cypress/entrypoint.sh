#!/bin/sh
# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Ensure correct permissions, as the bind-mount might currupt them (and prevent the installation)
[ -e /var/www/html/custom_apps ] && chown -R www-data:www-data /var/www/html/custom_apps > /dev/null 2>&1

NEXTCLOUD_UPDATE=1
export NEXTCLOUD_UPDATE
OUTPUT=$(/original_entrypoint.sh true)

echo "$OUTPUT"

# Check if new installed
G=$(echo "$OUTPUT" | grep "New nextcloud instance")
if [ $? -eq 0 ]; then
    echo "Nextcloud installed, fill demo data"
    su -s /bin/bash www-data -c "
        php /var/www/html/occ config:system:set debug --value='true' --type=boolean
        export OC_PASS=1234561
        php /var/www/html/occ user:add --password-from-env user1
        php /var/www/html/occ user:add --password-from-env user2
        php /var/www/html/occ app:enable circles
        php /var/www/html/occ app:enable viewer
        php /var/www/html/occ app:enable text
        php /var/www/html/occ app:enable --force collectives
        php /var/www/html/occ app:list
        for user in alice bob jane john; do \
            OC_PASS="\$user" php /var/www/html/occ user:add --password-from-env "\$user"; \
        done
        php /var/www/html/occ group:add "Bobs Group"
        for user in bob jane; do \
            php /var/www/html/occ group:adduser "Bobs Group" "\$user"; \
        done
        php /var/www/html/occ collectives:create SearchTest --owner=bob
        php /var/www/html/occ collectives:index
    "
fi

exec $@
