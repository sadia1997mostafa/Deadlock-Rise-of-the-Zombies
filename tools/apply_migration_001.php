<?php
// Apply migration 001_add_users_zone.sql
require_once __DIR__ . '/../config/db.php';
$mig = __DIR__ . '/../sql/migrations/001_add_users_zone.sql';
// We'll check for users.zone_id and add it if missing. This avoids relying on SQL dialect extensions.
try {
    // determine current DB name from PDO dsn
    $dbName = null;
    $attr = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    // fallback: use configured DB_NAME constant if available
    $dbName = getenv('DB_NAME') ?: null;
    // Better: parse from DSN
    $dsn = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    // Check information_schema for column
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'zone_id'");
    $st->execute();
    $exists = (int)$st->fetchColumn();
    if ($exists) {
        echo "Column users.zone_id already exists; nothing to do.\n";
        exit(0);
    }

    // add the column
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `zone_id` INT NULL DEFAULT NULL");
    echo "Added column users.zone_id\n";

    // Note: we skip adding an FK constraint here to avoid cross-version DDL issues.
    echo "Migration applied: users.zone_id added\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(2);
}
