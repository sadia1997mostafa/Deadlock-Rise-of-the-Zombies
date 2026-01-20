<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Dropping tables doctor_requests and volunteer_requests if they exist...\n";
    $pdo->exec("DROP TABLE IF EXISTS doctor_requests");
    $pdo->exec("DROP TABLE IF EXISTS volunteer_requests");
    echo "Done.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
