<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config.php';

// Autoloader for VariuxLink namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'VariuxLink\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    } else {
        // Optional: help debugging
        error_log("Autoloader could not find: $file for class $class");
    }
});

// Simple auth guard function
function requireLogin(): void
{
    if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
}

// ────────────────────────────────────────────────
// Routing
// ────────────────────────────────────────────────

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/login') {
    $ctrl = new VariuxLink\Controllers\AuthController();
    if ($method === 'POST') {
        $ctrl->handleLogin();
    } else {
        $ctrl->showLoginForm();
    }
} elseif ($path === '/logout') {
    $ctrl = new VariuxLink\Controllers\AuthController();
    $ctrl->logout();
} else {
    // All other routes require login
    requireLogin();

    if ($path === '/' || $path === '/dashboard') {
        $ctrl = new VariuxLink\Controllers\SyncController();
        $ctrl->dashboard();
    } elseif ($path === '/sync/notion') {
        $ctrl = new VariuxLink\Controllers\SyncController();
        $ctrl->syncNotion();
    } elseif ($path === '/time-entry/create') {
        $ctrl = new VariuxLink\Controllers\TimeEntryController();
        $ctrl->create();
    } else {
        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
    }
}