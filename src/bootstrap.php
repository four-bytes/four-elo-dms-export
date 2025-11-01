<?php

declare(strict_types=1);

// Autoload dependencies
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

foreach ($autoloadPaths as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

// Check required PHP extensions
$requiredExtensions = ['pdo', 'imagick'];
$missingExtensions = [];

foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $missingExtensions[] = $extension;
    }
}

if (!empty($missingExtensions)) {
    fwrite(
        STDERR,
        sprintf(
            "Error: Missing required PHP extensions: %s\n",
            implode(', ', $missingExtensions)
        )
    );
    exit(1);
}

// Check PDO ODBC driver availability (warning only)
if (extension_loaded('pdo') && !in_array('odbc', \PDO::getAvailableDrivers(), true)) {
    fwrite(
        STDERR,
        "Warning: PDO ODBC driver not available. MDB database access will not work.\n"
    );
}
