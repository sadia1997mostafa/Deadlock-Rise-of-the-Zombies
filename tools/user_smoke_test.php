<?php
// tools/user_smoke_test.php
// Simple smoke test that calls the user SQL helper functions (read-only) and reports results/errors.
// Usage: php tools/user_smoke_test.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/user_sql_functions.php';

$tests = [];

// We'll run read-only helpers only (avoid user_register_for_campaign which writes)
$tests[] = ['fn' => 'user_get_my_requests', 'args' => [$pdo, 1]]; // user id 1
$tests[] = ['fn' => 'user_get_active_campaigns', 'args' => [$pdo, null]];
$tests[] = ['fn' => 'user_get_equipment_availability', 'args' => [$pdo, null, null, 0]];
$tests[] = ['fn' => 'user_get_campaign_detail', 'args' => [$pdo, 0, 0]]; // pass 0 as campaign id; function will return null if not found
$tests[] = ['fn' => 'user_get_my_registrations', 'args' => [$pdo, 1]];
$tests[] = ['fn' => 'user_get_zone_summary', 'args' => [$pdo, null]];
$tests[] = ['fn' => 'user_search_campaigns_union', 'args' => [$pdo, 'test', 1, 5]];
$tests[] = ['fn' => 'user_get_campaigns_by_ids', 'args' => [$pdo, []]]; // empty -> []
$tests[] = ['fn' => 'user_get_campaigns_with_equipment', 'args' => [$pdo, true]];
$tests[] = ['fn' => 'user_cross_join_demo', 'args' => [$pdo, 10]];
$tests[] = ['fn' => 'user_get_campaigns_with_registration_counts', 'args' => [$pdo, 0]];
$tests[] = ['fn' => 'user_find_other_campaigns_same_zone', 'args' => [$pdo, 0, 5]];
$tests[] = ['fn' => 'user_campaigns_with_sufficient_equipment', 'args' => [$pdo, 0]];
$tests[] = ['fn' => 'user_get_campaigns_without_registrations', 'args' => [$pdo,]];

// Attempt to fetch a real campaign id and zone id to use for more meaningful runs
$sampleCampaignId = null;
$sampleZoneId = null;
try {
    $r = $pdo->query("SELECT id, zone_id FROM medical_campaign LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($r) { $sampleCampaignId = (int)$r['id']; if (!empty($r['zone_id'])) $sampleZoneId = (int)$r['zone_id']; }
} catch (Exception $e) { /* ignore */ }

if ($sampleCampaignId) {
    $tests[] = ['fn' => 'user_get_campaign_detail', 'args' => [$pdo, $sampleCampaignId, 1]];
    $tests[] = ['fn' => 'user_find_other_campaigns_same_zone', 'args' => [$pdo, $sampleCampaignId, 5]];
    $tests[] = ['fn' => 'user_campaigns_with_sufficient_equipment', 'args' => [$pdo, $sampleCampaignId]];
}

if ($sampleZoneId) {
    $tests[] = ['fn' => 'user_get_active_campaigns', 'args' => [$pdo, $sampleZoneId]];
    $tests[] = ['fn' => 'user_get_equipment_availability', 'args' => [$pdo, $sampleZoneId, null, 0]];
}

echo "Running user SQL helper smoke tests...\n\n";
foreach ($tests as $t) {
    $fn = $t['fn'];
    $args = $t['args'];
    echo "==> $fn : ";
    if (!function_exists($fn)) {
        echo "MISSING (function not defined)\n";
        continue;
    }
    try {
        $res = call_user_func_array($fn, $args);
        if (is_array($res)) {
            if (array() === $res) {
                echo "OK (empty array)\n";
            } else {
                echo "OK (rows=" . count($res) . ")\n";
            }
        } elseif (is_bool($res)) {
            echo "OK (bool)\n";
        } elseif ($res === null) {
            echo "OK (null)\n";
        } else {
            echo "OK (type=" . gettype($res) . ")\n";
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\nSmoke test finished.\n";
