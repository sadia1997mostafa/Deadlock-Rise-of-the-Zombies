<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/metrics.php';

// Central handler for user self-status actions (mark_safe, mark_infected, set_zone)
// This endpoint is registered as a raw action and intended to be POSTed to from
// both viewer and admin pages.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ?page=home'); exit;
}

require_login();
$uid = current_user_id();

// small helper to flash messages
function _flash($m) { $_SESSION['flash'] = $m; }

// mark safe
if (isset($_POST['mark_safe'])) {
    try {
        $st = $pdo->prepare("SELECT id FROM `safe` WHERE user_id = ? LIMIT 1"); $st->execute([$uid]); $sr = $st->fetch();
        if ($sr) {
            $pdo->prepare("UPDATE `safe` SET outbreak_status='safe', updated_at=NOW() WHERE id = ?")->execute([(int)$sr['id']]);
        } else {
            $pdo->prepare("INSERT INTO `safe` (user_id,name,created_at,outbreak_status) VALUES (?,?,NOW(),'safe')")->execute([$uid, 'User '.$uid]);
        }
        _flash('Marked you safe');
    } catch (Throwable $e) {
        _flash('Failed to mark safe');
    }
}

// mark infected
if (isset($_POST['mark_infected'])) {
    try {
        $stU = $pdo->prepare("SELECT zone_id FROM users WHERE id = ? LIMIT 1"); $stU->execute([$uid]); $uZone = (int)$stU->fetchColumn();
    } catch (Throwable $e) { $uZone = 0; }

    try {
        $st = $pdo->prepare("SELECT id FROM `safe` WHERE user_id = ? LIMIT 1"); $st->execute([$uid]); $sr = $st->fetch();
        if ($sr) {
            if ($uZone) {
                $pdo->prepare("UPDATE `safe` SET outbreak_status='infected', zone_id = ?, updated_at=NOW() WHERE id = ?")->execute([$uZone, (int)$sr['id']]);
            } else {
                $pdo->prepare("UPDATE `safe` SET outbreak_status='infected', updated_at=NOW() WHERE id = ?")->execute([(int)$sr['id']]);
            }
            $zoneForEvent = $uZone;
        } else {
            if ($uZone) {
                $pdo->prepare("INSERT INTO `safe` (user_id,name,zone_id,created_at,outbreak_status,updated_at) VALUES (?,?,?,?, 'infected', NOW())")->execute([$uid, 'User '.$uid, $uZone, date('Y-m-d H:i:s')]);
                $zoneForEvent = $uZone;
            } else {
                $pdo->prepare("INSERT INTO `safe` (user_id,name,created_at,outbreak_status,updated_at) VALUES (?,?,NOW(),'infected',NOW())")->execute([$uid, 'User '.$uid]);
                $zoneForEvent = 0;
            }
        }

        if (!empty($zoneForEvent)) {
            try {
                $ins = $pdo->prepare("INSERT INTO infection_events (zone_id, reporter_id, event_type, cases, created_at) VALUES (?, ?, ?, ?, NOW())");
                $ins->execute([$zoneForEvent, $uid, 'report', 1]);
            } catch (Throwable $e) { /* ignore */ }
            m_check_and_create_zone_alert($pdo, $zoneForEvent, (int)DANGER_ALERT_THRESHOLD);
        }
        _flash('Marked you infected');
    } catch (Throwable $e) {
        _flash('Failed to mark infected');
    }
}

// set zone
if (isset($_POST['set_zone'])) {
    $zoneId = isset($_POST['zone_id']) && $_POST['zone_id'] !== '' ? (int)$_POST['zone_id'] : 0;
    try {
        if ($zoneId) {
            $pdo->prepare("UPDATE users SET zone_id = ? WHERE id = ?")->execute([$zoneId, $uid]);
        } else {
            $pdo->prepare("UPDATE users SET zone_id = NULL WHERE id = ?")->execute([$uid]);
        }
    } catch (Throwable $e) { /* ignore */ }

    try {
        $st = $pdo->prepare("SELECT id FROM `safe` WHERE user_id = ? LIMIT 1"); $st->execute([$uid]); $sr = $st->fetch();
        if ($sr) {
            if ($zoneId) {
                $pdo->prepare("UPDATE `safe` SET zone_id = ?, updated_at=NOW() WHERE id = ?")->execute([$zoneId,(int)$sr['id']]);
            } else {
                $pdo->prepare("UPDATE `safe` SET zone_id = NULL, updated_at=NOW() WHERE id = ?")->execute([(int)$sr['id']]);
            }
        } else {
            if ($zoneId) {
                $pdo->prepare("INSERT INTO `safe` (user_id,name,zone_id,created_at) VALUES (?,?,?,NOW())")->execute([$uid, 'User '.$uid, $zoneId]);
            } else {
                $pdo->prepare("INSERT INTO `safe` (user_id,name,created_at) VALUES (?,?,NOW())")->execute([$uid, 'User '.$uid]);
            }
        }
        _flash('Saved your zone');
    } catch (Throwable $e) {
        _flash('Failed to save zone');
    }
}

// redirect back to referrer or home
$ref = $_SERVER['HTTP_REFERER'] ?? '?page=home';
header('Location: '.$ref);
exit;
