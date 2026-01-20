<?php
require_once __DIR__ . '/../config/db.php';
$st = $pdo->query("SHOW TABLES");
$rows = $st->fetchAll(PDO::FETCH_NUM);
echo "Tables in DB ($DB_NAME):\n";
foreach ($rows as $r) {
    echo " - " . $r[0] . "\n";
}
