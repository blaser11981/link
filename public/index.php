<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config.php';

// Manual class loading (no composer)
spl_autoload_register(function (string $class) {
    $prefix = 'VariuxLink\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Now you can use: new VariuxLink\Controllers\SyncController();