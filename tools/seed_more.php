<?php
// tools/seed_more.php
// Fill main tables until each has at least N rows (idempotent-ish)
require_once __DIR__ . '/../config/db.php';

$min = 10;
$fakerEmailIdx = 1;

try {
    $pdo->beginTransaction();

    // users
    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    while ($count < $min) {
        $name = "Demo User $fakerEmailIdx";
        $email = "demo{$fakerEmailIdx}@example.local";
        $hash = password_hash('DemoPass'.$fakerEmailIdx, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name,email,password_hash,created_at) VALUES (?,?,?,NOW())")->execute([$name,$email,$hash]);
        $count++; $fakerEmailIdx++;
    }

    // regions
    $count = (int)$pdo->query("SELECT COUNT(*) FROM regions")->fetchColumn();
    for ($i=1;$i<=$min-$count;$i++) {
        $pdo->prepare("INSERT INTO regions (name,created_at) VALUES (?,NOW())")->execute(["Demo Region $i"]);
    }

    // zones (attach to an existing region)
    $regionId = (int)$pdo->query("SELECT id FROM regions LIMIT 1")->fetchColumn();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM zones")->fetchColumn();
    for ($i=1;$i<=$min-$count;$i++) {
        $pdo->prepare("INSERT INTO zones (region_id,name,population,death_count,created_at) VALUES (?,?,?,?,NOW())")->execute([$regionId, "Demo Zone $i", rand(500,2000), rand(0,5)]);
    }

    // safe people
    $zoneId = (int)$pdo->query("SELECT id FROM zones LIMIT 1")->fetchColumn();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `safe`")->fetchColumn();
    for ($i=1;$i<=$min-$count;$i++) {
        // pick a user_id not already present in safe (to respect unique index)
        $uidRow = $pdo->query("SELECT u.id FROM users u LEFT JOIN `safe` s ON s.user_id = u.id WHERE s.user_id IS NULL LIMIT 1")->fetchColumn();
        if (!$uidRow) {
            // create a new user to assign
            $name = "Safe Person $i";
            $email = "safe{$i}@example.local";
            $hash = password_hash('SafePass'.$i, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (name,email,password_hash,created_at) VALUES (?,?,?,NOW())")->execute([$name,$email,$hash]);
            $uidRow = (int)$pdo->lastInsertId();
        }
        $pdo->prepare("INSERT INTO `safe` (user_id,name,age,gender,zone_id,outbreak_status,created_at) VALUES (?,?,?,?,?,'safe',NOW())")->execute([$uidRow, "Person $i", rand(18,70), (rand(0,1)?'male':'female'), $zoneId]);
    }

    // infection_events removed from runtime usage; skip seeding it.

    // alerts
    $count = (int)$pdo->query("SELECT COUNT(*) FROM alerts")->fetchColumn();
    for ($i=1;$i<=$min-$count;$i++) {
        $z = (int)$pdo->query("SELECT id FROM zones LIMIT 1 OFFSET " . rand(0, max(0,$min-1)))->fetchColumn();
        $pdo->prepare("INSERT INTO alerts (zone_id,title,status,created_at) VALUES (?,?, 'open', NOW())")->execute([$z, "Demo alert $i"]);
    }

    // campaigns (medical_campaign)
    $adminId = (int)$pdo->query("SELECT id FROM users WHERE email='admin@example.local' LIMIT 1")->fetchColumn();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM medical_campaign")->fetchColumn();
    for ($i=1;$i<=$min-$count;$i++) {
        $z = (int)$pdo->query("SELECT id FROM zones LIMIT 1 OFFSET " . rand(0, max(0,$min-1)))->fetchColumn();
        $pdo->prepare("INSERT INTO medical_campaign (title,description,creator_id,zone_id,state,capacity,created_at) VALUES (?,?,?,?,?,?,NOW())")->execute(["Demo Campaign $i","Description $i", $adminId, $z, 'todo', rand(10,200)]);
    }

    // campaign_registrations (medical_campaign_registrations)
    $campaignId = (int)$pdo->query("SELECT id FROM medical_campaign LIMIT 1")->fetchColumn();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM medical_campaign_registrations")->fetchColumn();
    for ($i=1;$i<=$min-$count;$i++) {
        $uid = (int)$pdo->query("SELECT id FROM users LIMIT 1 OFFSET " . rand(0, max(0,$min-1)))->fetchColumn();
        $cid = (int)$pdo->query("SELECT id FROM medical_campaign LIMIT 1 OFFSET " . rand(0, max(0,$min-1)))->fetchColumn();
        $pdo->prepare("INSERT IGNORE INTO medical_campaign_registrations (campaign_id,user_id,registered_at) VALUES (?,?,NOW())")->execute([$cid,$uid]);
    }

    // ambulance_requests
    $count = (int)$pdo->query("SELECT COUNT(*) FROM ambulance_requests")->fetchColumn();
    for ($i=1;$i<=$min-$count;$i++) {
        $uid = (int)$pdo->query("SELECT id FROM users LIMIT 1 OFFSET " . rand(0, max(0,$min-1)))->fetchColumn();
        $z = (int)$pdo->query("SELECT id FROM zones LIMIT 1 OFFSET " . rand(0, max(0,$min-1)))->fetchColumn();
        $pdo->prepare("INSERT INTO ambulance_requests (user_id,zone_id,details,status,created_at) VALUES (?,?,?, 'requested', NOW())")->execute([$uid,$z, "Need help $i"]);
    }

    // icu_requests
    $count = (int)$pdo->query("SELECT COUNT(*) FROM icu_requests")->fetchColumn();
    for ($i=1;$i<=$min-$count;$i++) {
        $uid = (int)$pdo->query("SELECT id FROM users LIMIT 1 OFFSET " . rand(0, max(0,$min-1)))->fetchColumn();
        $z = (int)$pdo->query("SELECT id FROM zones LIMIT 1 OFFSET " . rand(0, max(0,$min-1)))->fetchColumn();
        $pdo->prepare("INSERT INTO icu_requests (user_id,zone_id,details,status,created_at) VALUES (?,?,?, 'requested', NOW())")->execute([$uid,$z, "ICU $i"]);
    }

    // medical_equipments (previously vaccines)
    $count = (int)$pdo->query("SELECT COUNT(*) FROM medical_equipments")->fetchColumn();
    for ($i=1;$i<=$min-$count;$i++) {
        $pdo->prepare("INSERT INTO medical_equipments (name,description,stock,created_at) VALUES (?,?,?,NOW())")->execute(["Demo Equipment $i","Description $i", rand(0,500)]);
    }

    $pdo->commit();
    echo "Seeding to $min rows per table completed.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Seeding failed: " . $e->getMessage() . "\n";
}

?>