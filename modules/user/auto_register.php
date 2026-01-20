<?php
// Auto-register the current user for the best open campaign in their zone
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/user_sql_functions.php';

if (!is_logged_in()) { header('Location: ?page=login'); exit; }
$uid = current_user_id();

try {
    
    $zoneId = 0;
    $st = $pdo->prepare("SELECT zone_id FROM users WHERE id = ? LIMIT 1"); $st->execute([$uid]); $zoneId = (int)$st->fetchColumn();
    if (!$zoneId) {
        $st2 = $pdo->prepare("SELECT zone_id FROM `safe` WHERE user_id = ? LIMIT 1"); $st2->execute([$uid]); $zoneId = (int)$st2->fetchColumn();
    }

    if (!$zoneId) {
        $_SESSION['flash'] = 'Your zone is not set. Please set your zone in your profile first.';
        header('Location: ?page=user_panel'); exit;
    }


    $sql = "SELECT mc.id, mc.title, mc.capacity, COALESCE(COUNT(r.id),0) AS registrations,
                   (COALESCE(mc.capacity,0) - COALESCE(COUNT(r.id),0)) AS slots_left
            FROM medical_campaign mc
            LEFT JOIN medical_campaign_registrations r ON r.campaign_id = mc.id
            WHERE mc.zone_id = :zone_id AND mc.state <> 'done'
            GROUP BY mc.id
            HAVING slots_left > 0
            ORDER BY slots_left DESC, mc.created_at DESC
            LIMIT 1";
    $st3 = $pdo->prepare($sql); $st3->execute([':zone_id'=>$zoneId]); $best = $st3->fetch(PDO::FETCH_ASSOC);
    if (!$best) {
        $_SESSION['flash'] = 'No open campaigns with available slots found in your zone.';
        header('Location: ?page=user_panel'); exit;
    }

    
    $res = user_register_for_campaign($pdo, $uid, (int)$best['id']);
    $_SESSION['flash'] = $res['message'] ?? 'Registration attempt completed.';
    header('Location: ?page=user_panel'); exit;
} catch (Throwable $e) {
    $_SESSION['flash'] = 'Error during auto-register: ' . $e->getMessage();
    header('Location: ?page=user_panel'); exit;
}
