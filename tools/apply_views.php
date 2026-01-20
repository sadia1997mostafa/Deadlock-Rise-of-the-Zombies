<?php
// tools/apply_views.php
require_once __DIR__ . '/../config/db.php';
if (! isset($pdo) || ! $pdo instanceof PDO) {
    echo "DB connection not available\n";
    exit(1);
}
$sql = file_get_contents(__DIR__ . '/../sql/ddl_views.sql');
if ($sql === false) { echo "ddl_views.sql not found\n"; exit(1); }
$parts = preg_split('/;\s*(\r\n|\n|$)/', $sql);
foreach ($parts as $s) {
    $s = trim($s);
    if ($s === '') continue;
    try {
        $pdo->exec($s);
        echo "Executed DDL statement\n";
    } catch (PDOException $e) {
        echo "DDL failed: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
