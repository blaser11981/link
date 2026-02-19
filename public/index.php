<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config.php';

// PSR-4 style autoloader for VariuxLink namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'VariuxLink\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Basic routing – expand later
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

if ($path === '/' || $path === '/dashboard') {
    $controller = new VariuxLink\Controllers\SyncController();
    $controller->dashboard();
} elseif ($path === '/login') {
    $controller = new VariuxLink\Controllers\AuthController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->handleLogin();
    } else {
        $controller->showLogin();
    }
} elseif ($path === '/sync/notion') {
    $controller = new VariuxLink\Controllers\SyncController();
    $controller->syncNotion();
} else {
    http_response_code(404);
    echo '<h1>404 Not Found</h1>';
}
