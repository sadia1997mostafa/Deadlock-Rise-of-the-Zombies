<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
if (!is_super_admin($pdo, current_user_id())) { header('Location: ?page=home&err=role'); exit; }

// accept multiple possible form field names (legacy: survivor_id / person_id)
// accept posted identifiers or new person data
$sidRaw = $_POST['person_id'] ?? $_POST['survivor_id'] ?? $_POST['id'] ?? '';
$sid = is_numeric($sidRaw) ? (int)$sidRaw : 0;
$zone = (int)($_POST['zone_id'] ?? 0);
$personName = trim((string)($_POST['person_name'] ?? ''));
$gender = trim((string)($_POST['gender'] ?? ''));
// admin may request auto-creating a users row when no match is found
$createUserIfMissing = !empty($_POST['create_user_if_missing']);
// if no id provided and no name provided, abort
if (!$sid && $personName === '') { header('Location: ?page=home'); exit; }

// mark survivor infected
$pdo->beginTransaction();
try {
  // We accept either a safe.id or a users.id in the form field. Resolve to a safe row.
  $safeId = 0; $zoneFromSafe = 0;
  try {
    $st = $pdo->prepare("SELECT id, zone_id FROM `safe` WHERE id = ? LIMIT 1"); $st->execute([$sid]); $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r) { $safeId = (int)$r['id']; $zoneFromSafe = (int)($r['zone_id'] ?? 0); }
    else {
      // maybe admin provided a user_id instead; try to find a safe row by user_id
      $st2 = $pdo->prepare("SELECT id, zone_id FROM `safe` WHERE user_id = ? LIMIT 1"); $st2->execute([$sid]); $r2 = $st2->fetch(PDO::FETCH_ASSOC);
      if ($r2) { $safeId = (int)$r2['id']; $zoneFromSafe = (int)($r2['zone_id'] ?? 0); }
      else {
        // no safe row yet for this user id. If an admin provided a name, create a safe row
        // prefer explicit posted name/gender; otherwise, try to find a users.name when a user id was provided
        $st3 = $pdo->prepare("SELECT id, COALESCE(name,'User') AS name FROM users WHERE id = ? LIMIT 1");
        $st3->execute([$sid]);
        $u = $st3->fetch(PDO::FETCH_ASSOC);
        // determine name to use: posted name takes precedence, then users.name, then generic label
        if ($personName !== '') {
          $uname = $personName;
        } elseif ($u) {
          $uname = ($u['name'] ?: 'User '.$sid);
        } else {
          $uname = 'User '.($sid ?: time());
        }
        // If we don't have a user id but admin provided a name, try several strategies to
        // resolve the posted value to an existing users.id so safe.user_id can be linked.
        $userIdToInsert = $u ? $sid : null;
        if ($userIdToInsert === null && $personName !== '') {
          $p = trim($personName);
          try {
            // 1) exact match (case-insensitive)
            $stName = $pdo->prepare("SELECT id, name FROM users WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
            $stName->execute([$p]);
            $matchedUser = $stName->fetch(PDO::FETCH_ASSOC);
            // 2) if not found and looks like an email, try matching email
            if (!$matchedUser && strpos($p, '@') !== false) {
              $stMail = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
              $stMail->execute([$p]);
              $matchedUser = $stMail->fetch(PDO::FETCH_ASSOC);
            }
            // 3) fallback: partial match on name (first hit)
            if (!$matchedUser) {
              $stLike = $pdo->prepare("SELECT id, name FROM users WHERE name LIKE ? LIMIT 1");
              $stLike->execute(["%".$p."%"]);
              $matchedUser = $stLike->fetch(PDO::FETCH_ASSOC);
            }
            if ($matchedUser) {
              $userIdToInsert = (int)$matchedUser['id'];
              // prefer canonical name from users when admin didn't supply a name
              if ($personName === '') {
                $uname = $matchedUser['name'];
              }
              // If this user already has a safe row, reuse it instead of inserting a new one
              try {
                $stSafeUser = $pdo->prepare("SELECT id, zone_id FROM `safe` WHERE user_id = ? LIMIT 1");
                $stSafeUser->execute([$userIdToInsert]);
                $existingSafe = $stSafeUser->fetch(PDO::FETCH_ASSOC);
                if ($existingSafe) {
                  $safeId = (int)$existingSafe['id'];
                  $zoneFromSafe = (int)($existingSafe['zone_id'] ?? 0);
                }
              } catch (Throwable $__e) {
                // ignore and allow insertion below
              }
            }
            // If still no matched user but admin requested creation, create a minimal users row and link it
            if (!$matchedUser && $createUserIfMissing) {
              try {
                // generate a unique placeholder email
                $genEmail = 'user' . time() . mt_rand(1000,9999) . '@local';
                // create a random password and hash it (user can reset later)
                $randPass = bin2hex(random_bytes(8));
                $passHash = password_hash($randPass, PASSWORD_DEFAULT);
                $insU = $pdo->prepare("INSERT INTO users (name,email,password_hash,is_active,created_at) VALUES (?, ?, ?, 0, NOW())");
                $insU->execute([$personName ?: $uname, $genEmail, $passHash]);
                $userIdToInsert = (int)$pdo->lastInsertId();
                // note: we created a placeholder user (inactive). No email sent.
              } catch (Throwable $__e) {
                // if user creation fails, keep proceeding without linking
              }
            }
          } catch (Throwable $e) {
            // lookup failed; leave userIdToInsert as null and continue
          }
        }
        // gender: only accept known values to avoid DB errors
        $allowedGenders = ['male','female','other',''];
        $genderToInsert = in_array($gender, $allowedGenders, true) ? ($gender ?: null) : null;
        // use posted zone if available
        $insertZone = $zone ?: null;
  $ins = $pdo->prepare("INSERT INTO `safe` (user_id,name,gender,zone_id,outbreak_status,created_at,updated_at) VALUES (?, ?, ?, ?, 'infected', NOW(), NOW())");
  $ins->execute([$userIdToInsert, $uname, $genderToInsert, $insertZone]);
        $safeId = (int)$pdo->lastInsertId();
        $zoneFromSafe = (int)($insertZone ?? 0);
      }
    }
    // ensure the safe row is marked infected and timestamped
    if ($safeId) {
      $pdo->prepare("UPDATE `safe` SET outbreak_status='infected', updated_at=NOW() WHERE id=?")->execute([$safeId]);
    }
  } catch (Throwable $e) {
    // non-fatal; continue to other logic
    $safeId = 0; $zoneFromSafe = 0;
  }
  // optionally create an infection_event if zone supplied
  // determine a zone to attribute the infection event to. prefer explicit POSTed zone,
  // otherwise try to use the existing safe.zone_id for the person.
  // determine a zone to attribute the infection event to. prefer explicit POSTed zone,
  // otherwise try to use the existing safe.zone_id for the person (resolved above).
  $zoneForEvent = (int)$zone;
  if (!$zoneForEvent) {
    $zoneForEvent = (int)($zoneFromSafe ?? 0);
  }

  // If we have a zone, ensure there's a recent infection_events row for it; if not, insert one.
  if ($zoneForEvent) {
    try {
      // avoid inserting duplicate events for the same zone within the last 24 hours
      $chk = $pdo->prepare("SELECT id FROM infection_events WHERE zone_id = ? AND created_at >= NOW() - INTERVAL 24 HOUR LIMIT 1");
      $chk->execute([$zoneForEvent]);
      $exists = (bool)$chk->fetch();
      if (!$exists) {
        $reporter = current_user_id() ? (int)current_user_id() : null;
        $ins = $pdo->prepare("INSERT INTO infection_events (zone_id, reporter_id, event_type, cases, created_at) VALUES (?, ?, ?, ?, NOW())");
        $ins->execute([$zoneForEvent, $reporter, 'report', 1]);
      }
    } catch (Throwable $e) {
      // non-fatal: don't block marking a person infected if events table/insert fails
    }

    // rely on safe.updated_at for recent infection timing; optionally create an alert
    m_check_and_create_zone_alert($pdo, $zoneForEvent, (int)DANGER_ALERT_THRESHOLD);
  }
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // surface the error to the admin via flash so it's visible in the UI
  if (session_status() === PHP_SESSION_NONE) session_start();
  $_SESSION['flash'] = 'Error marking infected: ' . $e->getMessage();
  header('Location: ?page=home'); exit;
}
header('Location: ?page=home'); exit;
