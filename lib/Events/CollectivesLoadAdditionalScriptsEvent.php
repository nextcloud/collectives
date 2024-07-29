<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Events;

use OCP\EventDispatcher\Event;

/**
 * This event is triggered when the Collectives app is rendered.
 * It can be used to add additional scripts to the Collectives app.
 */
class CollectivesLoadAdditionalScriptsEvent extends Event {
}
