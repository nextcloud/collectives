<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('collectives', 'collectives-main', 'text');
Util::addStyle('collectives', 'collectives-main');
?>

<div id="q-app"></div>

<?php if (isset($_['token'])) {
	print_unescaped('<input id="isPublic" type="hidden" name="isPublic" value="1">');
	print_unescaped('<input id="sharingToken" type="hidden" value="' . $_['token'] . '">');
} ?>
