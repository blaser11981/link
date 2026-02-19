<?php
declare(strict_types=1);

use VariuxLink\Controllers\AuthController;
use VariuxLink\Controllers\SyncController;
use VariuxLink\Controllers\TimeEntryController;

// Very basic router – expand later with a proper one if needed

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/login') {
    if ($method === 'GET') {
        (new AuthController())->showLoginForm();
    } elseif ($method === 'POST') {
        (new AuthController())->handleLogin();
    }
} elseif ($uri === '/logout') {
    (new AuthController())->logout();
} elseif ($uri === '/dashboard' || $uri === '/') {
    (new SyncController())->dashboard();
} elseif ($uri === '/sync/notion') {
    (new SyncController())->syncFromNotion();
} elseif ($uri === '/time-entry/create') {
    (new TimeEntryController())->create();
} else {
    http_response_code(404);
    echo '<h1>404 Not Found</h1>';
}