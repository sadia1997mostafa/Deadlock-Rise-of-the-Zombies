<?php
require_once __DIR__ . '/../config/db.php';

try {
    // Check if `medical_equipments` table has a `stock` column
    $row = $pdo->query("SHOW COLUMNS FROM medical_equipments LIKE 'stock'")->fetch();
    if ($row) {
        echo "stock column already exists\n";
        exit(0);
    }

    // Add the stock column
    $pdo->exec("ALTER TABLE medical_equipments ADD COLUMN stock INT NOT NULL DEFAULT 0");
    echo "stock column added\n";
    exit(0);
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
