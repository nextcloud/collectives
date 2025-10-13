<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
?>

<div class="emptycontent" style="
	min-height: 20vw;
	width: 100%;
	max-width: 700px;
	margin-block: 10vh auto;
	margin-inline: auto;
	background-color: var(--color-main-background-blur);
	color: var(--color-main-text);
	padding: calc(3 * var(--default-grid-baseline));
	border-radius: var(--border-radius-container);
	">
	<div>
		<h2><?php print_unescaped($l->t('Error: Missing apps')); ?></h2>
		<?php print_unescaped($l->t('The following dependency apps are missing:')); ?>
	</div>
	<br />
	<ul style="font-weight: bold;">
		<?php
$len = count($_['appsMissing']);
foreach ($_['appsMissing'] as $app) {
	print_unescaped('<li>' . $app . '</li>');
}
?>
	</ul>
	<br />
	<div>
		<?php print_unescaped($l->t('Please ask the administrator to enable these apps.')); ?>
	</div>
</div>

