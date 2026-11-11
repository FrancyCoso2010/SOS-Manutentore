<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Activity.php';

date_default_timezone_set('Europe/Rome');

Database::init($config['db']);

$timezone = Settings::get('timezone', 'Europe/Rome');

if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
    date_default_timezone_set($timezone);
}

Auth::startSession($config['session']);

require_once __DIR__ . '/layout.php';

return $config;
