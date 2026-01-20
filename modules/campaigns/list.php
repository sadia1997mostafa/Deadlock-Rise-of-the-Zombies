<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

$currentUser = current_user_id();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin($pdo, $currentUser) && isset($_POST['change_state'])) {
  $cid = (int)($_POST['campaign_id'] ?? 0);
  $newState = $_POST['state'] ?? '';
  $allowed = ['todo','in_progress','done'];
    if ($cid && in_array($newState, $allowed, true)) {
    $st = $pdo->prepare("UPDATE medical_campaign SET state = ? WHERE id = ?");
    $st->execute([$newState, $cid]);
  }
  header('Location: ?page=campaigns'); exit;
}

// List campaigns (simple public listing)
$sql = "SELECT c.*, u.name AS creator_name, z.name AS zone_name
  FROM medical_campaign c
        LEFT JOIN users u ON c.creator_id = u.id
        LEFT JOIN zones z ON c.zone_id = z.id
        ORDER BY c.created_at DESC";
$campaigns = $pdo->query($sql)->fetchAll();

?>
<h2>Medical Campaigns</h2>
<?php if (is_admin($pdo, $currentUser)): ?>
  <a class="btn" href="?page=campaign_create">Create Medical Campaign</a>
<?php endif; ?>
<table class="table" style="margin-top:8px;">
  <thead><tr><th>Title</th><th>Zone</th><th>Creator</th><th>State</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($campaigns as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['title']) ?></td>
        <td><?= htmlspecialchars($c['zone_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($c['creator_name'] ?? '') ?></td>
        <td>
          <?php if (is_admin($pdo, $currentUser)): ?>
            <form method="post" style="display:inline-block">
              <input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">
              <select name="state">
                <option value="todo" <?= $c['state']==='todo' ? 'selected' : '' ?>>todo</option>
                <option value="in_progress" <?= $c['state']==='in_progress' ? 'selected' : '' ?>>in_progress</option>
                <option value="done" <?= $c['state']==='done' ? 'selected' : '' ?>>done</option>
              </select>
              <button class="btn" name="change_state">Set</button>
            </form>
          <?php else: ?>
            <?= htmlspecialchars($c['state']) ?>
          <?php endif; ?>
        </td>
        <td><a class="btn" href="?page=campaign_view&id=<?= (int)$c['id'] ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
  <?php if (!$campaigns): ?><tr><td colspan="5"><em>No medical campaigns yet.</em></td></tr><?php endif; ?>
  </tbody>
</table>
