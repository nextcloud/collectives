<?php

$nextcloud_source = getenv('NEXTCLOUD_SOURCE') ?:
  __DIR__.'/../../..';
require_once $nextcloud_source.'/tests/bootstrap.php';

// Fix for "Autoload path not allowed: .../collectives/tests/testcase.php"
\OC_App::loadApp('collectives');
OC_Hook::clear();
