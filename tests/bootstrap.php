<?php

require_once __DIR__.'/../../../tests/bootstrap.php';

// Fix for "Autoload path not allowed: .../wiki/tests/testcase.php"
\OC_App::loadApp('wiki');
OC_Hook::clear();
