<?php

require_once __DIR__ . '/vendor/autoload.php';

$databasePath = '/mnt/d/ELOView/Archivdata/DMS.MDB';
$dsn = sprintf('odbc:Driver=MDBTools;DBQ=%s;', realpath($databasePath));

try {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT * FROM objkeys LIMIT 10");
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($keys) . " records\n\n";

    foreach ($keys as $key) {
        echo "parentid: {$key['parentid']}, okeyname: '{$key['okeyname']}', okeydata: '{$key['okeydata']}'\n";
        print_r($key);
        echo "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
