<?php
$nextcloud_source = getenv('NEXTCLOUD_SOURCE') ?:
  __DIR__.'/../../..';
require_once $nextcloud_source.'/tests/bootstrap.php';

// Fix for "Autoload path not allowed: .../wiki/tests/testcase.php"
\OC_App::loadApp('wiki');
OC_Hook::clear();
