<?php
declare(strict_types=1);

// ────────────────────────────────────────────────
// Simple .env loader – no dependencies
// ────────────────────────────────────────────────

$envFile = __DIR__ . '/.env';

if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2) + [1 => ''];
        $name  = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        putenv("$name=" . $value);
        $_ENV[$name]   = $value;
        $_SERVER[$name] = $value;
    }
}

// ────────────────────────────────────────────────
// Required constants with fallbacks
// ────────────────────────────────────────────────

define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_NAME',     getenv('DB_NAME')     ?: 'link');
define('DB_USER',     getenv('DB_USER')     ?: 'root');
define('DB_PASS',     getenv('DB_PASS')     ?: '');
define('NOTION_TOKEN', getenv('NOTION_TOKEN') ?: '');

define('APP_URL',     rtrim(getenv('APP_URL') ?: 'http://localhost', '/'));

// Notion database IDs (from your original document – cleaned)
define('NOTION_CLIENTS_DB_ID',      '2c4db8f834343434343400b733c87ec');
define('NOTION_PROJECTS_DB_ID',     '2c3db8f83434343434b8e52b323');
define('NOTION_TASKS_DB_ID',        '2c3db8434346bc8a8e');
define('NOTION_TIME_ENTRIES_DB_ID', '2c4db344346c');
// add others when you implement them (CRM, Parties, Sprints, Workstreams, ...)

if (empty(NOTION_TOKEN)) {
    trigger_error('NOTION_TOKEN is empty – Notion sync will fail', E_USER_WARNING);
}
