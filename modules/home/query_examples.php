<?php
// Home/runtime query examples for demo and testing
require_once __DIR__ . '/../../config/db.php';

if (! isset($pdo) || ! $pdo instanceof PDO) {
    echo "DB connection not available (check config/db.php)\n";
    exit(1);
}

function run_sql(PDO $pdo, $sql, $params = []) {
    echo "\n--- SQL: " . preg_replace('/\s+/', ' ', trim($sql)) . "\n";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) foreach ($rows as $r) print_r($r); else echo "(no rows)\n";
}

echo "User Query Examples (runtime)\n";

// 1) Marked infected/safe people list
run_sql($pdo, "SELECT id, name, outbreak_status, zone_id FROM `safe` ORDER BY updated_at DESC LIMIT 20");

// 2) Danger zones (cases in last 24h exceed threshold)
run_sql($pdo, "SELECT zone_id, SUM(cases) AS cases_24h FROM infection_events WHERE created_at >= NOW() - INTERVAL 1 DAY GROUP BY zone_id HAVING SUM(cases) >= 10");

// 3) List active campaigns for the user's zone (demo zone_id=1)
run_sql($pdo, 'SELECT * FROM medical_campaign WHERE state <> "done" AND (zone_id = ? OR zone_id IS NULL) LIMIT 20', [1]);

// 4) Register for a campaign (demo, not idempotent)
try {
    $campaignId = 1;
    $userId = 1;
    $stmt = $pdo->prepare('INSERT IGNORE INTO medical_campaign_registrations (campaign_id, user_id) VALUES (?, ?)');
    $stmt->execute([$campaignId, $userId]);
    echo "Registered user {$userId} for campaign {$campaignId}\n";
} catch (Exception $e) {
    echo "Registration failed: " . $e->getMessage() . "\n";
}

// 5) Request ambulance / ICU (insert example)
try {
    $stmt = $pdo->prepare('INSERT INTO ambulance_requests (user_id, zone_id, details) VALUES (?, ?, ?)');
    $stmt->execute([1, 1, 'Demo ambulance request']);
    echo "Ambulance request created\n";
} catch (Exception $e) {
    echo "Ambulance request failed: " . $e->getMessage() . "\n";
}

echo "\nHome examples complete.\n";

?>
