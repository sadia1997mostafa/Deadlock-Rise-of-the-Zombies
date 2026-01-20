<?php
// Apply migration 002: add helpful indexes and foreign keys where safe
require_once __DIR__ . '/../config/db.php';

function index_exists(PDO $pdo, $table, $index_name) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $st->execute([$table, $index_name]);
    return (int)$st->fetchColumn() > 0;
}

function constraint_exists(PDO $pdo, $table, $constraint_name) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?");
    $st->execute([$table, $constraint_name]);
    return (int)$st->fetchColumn() > 0;
}

$changes = [];
try {
    // 1) Indexes: safe(user_id)
    if (!index_exists($pdo, 'safe', 'idx_safe_user_id')) {
        $pdo->exec("CREATE INDEX idx_safe_user_id ON safe(user_id)");
        $changes[] = 'Created index idx_safe_user_id on safe(user_id)';
    } else { $changes[] = 'Index idx_safe_user_id already exists'; }

    if (!index_exists($pdo, 'safe', 'idx_safe_zone_id')) {
        $pdo->exec("CREATE INDEX idx_safe_zone_id ON safe(zone_id)");
        $changes[] = 'Created index idx_safe_zone_id on safe(zone_id)';
    } else { $changes[] = 'Index idx_safe_zone_id already exists'; }

    // medical_campaign_registrations indexes
    if (!index_exists($pdo, 'medical_campaign_registrations', 'idx_cr_campaign_id')) {
        $pdo->exec("CREATE INDEX idx_cr_campaign_id ON medical_campaign_registrations(campaign_id)");
        $changes[] = 'Created index idx_cr_campaign_id on medical_campaign_registrations(campaign_id)';
    } else { $changes[] = 'Index idx_cr_campaign_id already exists'; }

    if (!index_exists($pdo, 'medical_campaign_registrations', 'idx_cr_campaign_user')) {
        $pdo->exec("CREATE INDEX idx_cr_campaign_user ON medical_campaign_registrations(campaign_id, user_id)");
        $changes[] = 'Created index idx_cr_campaign_user on medical_campaign_registrations(campaign_id,user_id)';
    } else { $changes[] = 'Index idx_cr_campaign_user already exists'; }

    // 2) Optional unique safety check for safe.user_id
    $stDup = $pdo->query("SELECT COUNT(*) FROM (SELECT user_id, COUNT(*) AS c FROM safe GROUP BY user_id HAVING c > 1) x");
    $dupCount = (int)$stDup->fetchColumn();
    if ($dupCount === 0) {
        if (!index_exists($pdo, 'safe', 'uq_safe_user_id')) {
            // create unique index
            $pdo->exec("CREATE UNIQUE INDEX uq_safe_user_id ON safe(user_id)");
            $changes[] = 'Created unique index uq_safe_user_id on safe(user_id)';
        } else { $changes[] = 'Unique index uq_safe_user_id already exists'; }
    } else {
        $changes[] = "Skipped creating UNIQUE index on safe(user_id) because $dupCount duplicate user_id values exist";
    }

    // 3) Foreign keys: add only if safe to do so (no orphan rows)
    // users.zone_id -> zones.id
    if (!constraint_exists($pdo, 'users', 'fk_users_zone')) {
        // ensure zones table exists
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zones'");
        $st->execute();
        if ((int)$st->fetchColumn() > 0) {
            $stOrphans = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN zones z ON u.zone_id = z.id WHERE u.zone_id IS NOT NULL AND z.id IS NULL");
            $stOrphans->execute();
            $orphans = (int)$stOrphans->fetchColumn();
            if ($orphans === 0) {
                try {
                    $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL");
                    $changes[] = 'Added FK fk_users_zone (users.zone_id -> zones.id)';
                } catch (Throwable $e) { $changes[] = 'Failed to add fk_users_zone: '.$e->getMessage(); }
            } else {
                $changes[] = "Skipped fk_users_zone: $orphans orphan users.zone_id values reference missing zones";
            }
        } else { $changes[] = 'Skipped fk_users_zone: zones table not found'; }
    } else { $changes[] = 'Constraint fk_users_zone already exists'; }

    // safe.zone_id -> zones.id
    if (!constraint_exists($pdo, 'safe', 'fk_safe_zone')) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zones'");
        $st->execute();
        if ((int)$st->fetchColumn() > 0) {
            $stOrphans = $pdo->prepare("SELECT COUNT(*) FROM safe s LEFT JOIN zones z ON s.zone_id = z.id WHERE s.zone_id IS NOT NULL AND z.id IS NULL");
            $stOrphans->execute();
            $orphans = (int)$stOrphans->fetchColumn();
            if ($orphans === 0) {
                try {
                    $pdo->exec("ALTER TABLE safe ADD CONSTRAINT fk_safe_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL");
                    $changes[] = 'Added FK fk_safe_zone (safe.zone_id -> zones.id)';
                } catch (Throwable $e) { $changes[] = 'Failed to add fk_safe_zone: '.$e->getMessage(); }
            } else {
                $changes[] = "Skipped fk_safe_zone: $orphans orphan safe.zone_id values reference missing zones";
            }
        } else { $changes[] = 'Skipped fk_safe_zone: zones table not found'; }
    } else { $changes[] = 'Constraint fk_safe_zone already exists'; }

    // safe.user_id -> users.id
    if (!constraint_exists($pdo, 'safe', 'fk_safe_user')) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'");
        $st->execute();
        if ((int)$st->fetchColumn() > 0) {
            $stOrphans = $pdo->prepare("SELECT COUNT(*) FROM safe s LEFT JOIN users u ON s.user_id = u.id WHERE s.user_id IS NOT NULL AND u.id IS NULL");
            $stOrphans->execute();
            $orphans = (int)$stOrphans->fetchColumn();
            if ($orphans === 0) {
                try {
                    $pdo->exec("ALTER TABLE safe ADD CONSTRAINT fk_safe_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
                    $changes[] = 'Added FK fk_safe_user (safe.user_id -> users.id)';
                } catch (Throwable $e) { $changes[] = 'Failed to add fk_safe_user: '.$e->getMessage(); }
            } else {
                $changes[] = "Skipped fk_safe_user: $orphans orphan safe.user_id values reference missing users";
            }
        } else { $changes[] = 'Skipped fk_safe_user: users table not found'; }
    } else { $changes[] = 'Constraint fk_safe_user already exists'; }

    echo "Migration 002 results:\n";
    foreach ($changes as $c) echo " - $c\n";
    exit(0);

} catch (Throwable $e) {
    echo "Migration 002 failed: " . $e->getMessage() . "\n";
    exit(2);
}

