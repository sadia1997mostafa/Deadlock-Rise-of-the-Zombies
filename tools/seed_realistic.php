<?php
// tools/seed_realistic.php
// Create a more realistic dataset for demos and testing
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    // create admin if needed
    $adminEmail = 'admin@example.local';
    $adminId = $pdo->query("SELECT id FROM users WHERE email = '".$adminEmail."' LIMIT 1")->fetchColumn();
    if (!$adminId) {
        $hash = password_hash('AdminPass123!', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name,email,password_hash,created_at) VALUES (?,?,?,NOW())")->execute(['Admin User',$adminEmail,$hash]);
        $adminId = (int)$pdo->lastInsertId();
    }

    // create regions & zones (3 regions, 6 zones)
    $regions = ['Northshire','Eastfield','Southport'];
    $zoneNames = ['Northville','Riverside','East Heights','Lakeview','South Market','Harbor Point'];
    foreach ($regions as $r) {
        $pdo->prepare("INSERT IGNORE INTO regions (name,created_at) VALUES (?,NOW())")->execute([$r]);
    }
    $regionIds = $pdo->query("SELECT id FROM regions ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($zoneNames as $i=>$zn) {
        $rid = $regionIds[$i % count($regionIds)];
        $pdo->prepare("INSERT IGNORE INTO zones (region_id,name,population,death_count,created_at) VALUES (?,?,?,?,NOW())")->execute([$rid,$zn, rand(1000,15000), rand(0,20)]);
    }

    $zoneIds = $pdo->query("SELECT id FROM zones ORDER BY id LIMIT 6")->fetchAll(PDO::FETCH_COLUMN);

    // create 50 users with professions and create safe rows
    $professions = ['Teacher','Nurse','Cashier','Driver','Farmer','Engineer','Student','Guard','Clerk','Store Manager'];
    for ($i=1;$i<=50;$i++) {
        $name = "Demo Person $i";
        $email = "person{$i}@example.local";
        $hash = password_hash('Pass'.$i, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT IGNORE INTO users (name,email,password_hash,created_at) VALUES (?,?,?,NOW())")->execute([$name,$email,$hash]);
    }
    $userIds = $pdo->query("SELECT id FROM users ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);

    // insert safe people with professions and skills where schema supports
    $existingSafe = (int)$pdo->query("SELECT COUNT(*) FROM `safe`")->fetchColumn();
    $toAdd = max(0, 40 - $existingSafe);
    $randIdx = 0;
    for ($i=0;$i<$toAdd;$i++) {
        $uid = $userIds[$i % count($userIds)];
        $zone = $zoneIds[$i % count($zoneIds)];
        $prof = $professions[$i % count($professions)];
        $age = rand(18,70);
        $gender = (rand(0,1)?'male':'female');
        // note: older schema may not have profession/skill; guard with try
        try {
            $pdo->prepare("INSERT IGNORE INTO `safe` (user_id,name,age,gender,profession,skill,zone_id,outbreak_status,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")->execute([$uid,"Person $uid",$age,$gender,$prof,'General',$zone,'safe']);
        } catch (Exception $e) {
            $pdo->prepare("INSERT IGNORE INTO `safe` (user_id,name,age,gender,zone_id,outbreak_status,created_at) VALUES (?,?,?,?,?,?,NOW())")->execute([$uid,"Person $uid",$age,$gender,$zone,'safe']);
        }
    }

    // infection_events time-series removed: we rely on `safe.updated_at` for recent infection activity

    // medical_equipments
    for ($i=1;$i<=10;$i++) {
        $pdo->prepare("INSERT IGNORE INTO medical_equipments (name,description,stock,created_at) VALUES (?,?,?,NOW())")->execute(["Equip-$i","Demo equipment $i", rand(0,200)]);
    }

    // medical_campaigns and registrations (some in_progress)
    for ($i=1;$i<=8;$i++) {
        $z = $zoneIds[$i % count($zoneIds)];
        $state = ($i % 3 == 0) ? 'in_progress' : 'todo';
        $cap = rand(10,100);
        $pdo->prepare("INSERT IGNORE INTO medical_campaign (title,description,creator_id,zone_id,state,capacity,created_at) VALUES (?,?,?,?,?,?,NOW())")->execute(["Campaign $i","Equipment distribution $i", $adminId, $z, $state, $cap]);
    }
    $campaignIds = $pdo->query("SELECT id FROM medical_campaign ORDER BY id LIMIT 8")->fetchAll(PDO::FETCH_COLUMN);
    // register some users
    foreach ($campaignIds as $cid) {
        for ($j=0;$j<rand(5,20);$j++) {
            $uid = $userIds[array_rand($userIds)];
            $pdo->prepare("INSERT IGNORE INTO medical_campaign_registrations (campaign_id,user_id,registered_at) VALUES (?,?,NOW())")->execute([$cid,$uid]);
        }
    }

    // ambulance/icu requests varied statuses
    foreach (array_slice($userIds,0,12) as $k=>$uid) {
        $z = $zoneIds[$k % count($zoneIds)];
        $pdo->prepare("INSERT IGNORE INTO ambulance_requests (user_id,zone_id,details,status,created_at) VALUES (?,?,?,?,NOW())")->execute([$uid,$z,"Help needed","requested"]);
        if ($k % 4 == 0) {
            $pdo->prepare("INSERT IGNORE INTO ambulance_requests (user_id,zone_id,details,status,created_at) VALUES (?,?,?,?,NOW())")->execute([$uid,$z,"Resolved","completed"]);
        }
    }
    foreach (array_slice($userIds,12,8) as $k=>$uid) {
        $z = $zoneIds[$k % count($zoneIds)];
        $pdo->prepare("INSERT IGNORE INTO icu_requests (user_id,zone_id,details,status,created_at) VALUES (?,?,?,?,NOW())")->execute([$uid,$z,"ICU needed","requested"]);
    }

    $pdo->commit();
    echo "Realistic seed applied.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Realistic seed failed: " . $e->getMessage() . "\n";
}

?>