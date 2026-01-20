<?php
// modules/campaigns/view.php
require_once __DIR__ . '/../../config/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$uid = current_user_id();

$sql = "SELECT c.*, u.name AS creator_name, z.name AS zone_name
        FROM campaigns c
        LEFT JOIN users u ON c.creator_id = u.id
        LEFT JOIN zones z ON c.zone_id = z.id
        WHERE c.id = ?";
$st = $pdo->prepare($sql);
$st->execute([$id]);
$c = $st->fetch();
if (!$c) { echo "<p>Campaign not found.</p>"; exit; }

// register/unregister actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['register'])) {
    $sql = "INSERT IGNORE INTO campaign_registrations (campaign_id,user_id) VALUES (?,?)";
    $pdo->prepare($sql)->execute([$id,$uid]);
  } elseif (isset($_POST['cancel'])) {
    $sql = "DELETE FROM campaign_registrations WHERE campaign_id = ? AND user_id = ?";
    $pdo->prepare($sql)->execute([$id,$uid]);
  }
  header("Location: ?page=campaign_view&id=$id"); exit;
}

$regs = $pdo->prepare("SELECT COUNT(*) FROM campaign_registrations WHERE campaign_id = ?");
$regs->execute([$id]); $regCount = (int)$regs->fetchColumn();

$chk = $pdo->prepare("SELECT 1 FROM campaign_registrations WHERE campaign_id = ? AND user_id = ? LIMIT 1");
$chk->execute([$id,$uid]); $isRegistered = (bool)$chk->fetchColumn();

// fetch attendees list
$attStmt = $pdo->prepare("SELECT u.id,u.name FROM campaign_registrations cr JOIN users u ON cr.user_id=u.id WHERE cr.campaign_id = ? LIMIT 50");
$attStmt->execute([$id]); $attendees = $attStmt->fetchAll();

?>
<h2><?= htmlspecialchars($c['title']) ?></h2>
<p><?= nl2br(htmlspecialchars($c['description'])) ?></p>
<p>Zone: <?= htmlspecialchars($c['zone_name'] ?? 'Any') ?> | State: <?= htmlspecialchars($c['state']) ?> | Capacity: <?= htmlspecialchars($c['capacity'] ?? 'unlimited') ?></p>

<form method="post">
  <?php if (!$isRegistered): ?>
    <button class="btn" name="register">Register</button>
  <?php else: ?>
    <button class="btn alt" name="cancel">Cancel registration</button>
  <?php endif; ?>
  <a class="btn" href="?page=campaigns">Back</a>
</form>

<h3>Attendees (<?= $regCount ?>)</h3>
<ul>
  <?php foreach ($attendees as $a): ?><li><?= htmlspecialchars($a['name']) ?></li><?php endforeach; ?>
  <?php if (!$attendees): ?><li><em>No attendees yet.</em></li><?php endif; ?>
</ul>
