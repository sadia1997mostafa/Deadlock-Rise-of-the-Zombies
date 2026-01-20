<?php
// tools/seed_demo.php
// Run in CLI: php tools/seed_demo.php
require_once __DIR__ . '/../config/db.php';

$password_admin = 'AdminPass123!';
$password_doctor = 'DoctorPass123!';

try {
    $pdo->beginTransaction();

    // create admin user (single-admin approach)
    $hashAdmin = password_hash($password_admin, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT IGNORE INTO users (name,email,password_hash,created_at) VALUES (?,?,?,NOW())")->execute(['Admin User','admin@example.local',$hashAdmin]);
    $adminId = (int)$pdo->query("SELECT id FROM users WHERE email='admin@example.local' LIMIT 1")->fetchColumn();

    // (No doctor user seeded — campaigns and vaccine management are admin-only now.)

    // ensure at least one region/zone
    $regionId = $pdo->query("SELECT id FROM regions LIMIT 1")->fetchColumn();
    if (!$regionId) {
        $pdo->prepare("INSERT INTO regions (name,created_at) VALUES (?,NOW())")->execute(['Demo Region']);
        $regionId = (int)$pdo->lastInsertId();
    }
    $zoneId = $pdo->query("SELECT id FROM zones LIMIT 1")->fetchColumn();
    if (!$zoneId) {
        $pdo->prepare("INSERT INTO zones (region_id,name,population,death_count,created_at) VALUES (?,?,?,?,NOW())")->execute([$regionId,'Demo Zone',1000,0]);
        $zoneId = (int)$pdo->lastInsertId();
    }

    // create a sample medical campaign by admin
    $pdo->prepare("INSERT IGNORE INTO medical_campaign (title,description,creator_id,zone_id,state,capacity,created_at) VALUES (?,?,?,?,?,?,NOW())")->execute([
        'Demo Equipment Drive','Free demo equipment', $adminId, $zoneId, 'todo', 100
    ]);

    $pdo->commit();

    echo "Seeded admin (admin@example.local / $password_admin)\n";
    echo "Demo zone id: $zoneId\n";
    echo "Done.\n";
    echo "Demo zone id: $zoneId\n";
    echo "Done.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Seeding failed: " . $e->getMessage() . "\n";
}
