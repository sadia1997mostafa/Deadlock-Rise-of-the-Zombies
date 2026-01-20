<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
// Only super-admins or watch/ops roles can manage alerts
if (!(is_super_admin($pdo, current_user_id()) || in_array(get_acting_role(), ['watch_officer','ops_admin'], true))) {
  header('Location: ?page=home'); exit;
}

$stmt = $pdo->query("SELECT a.*, z.name AS zone_name
  FROM alerts a
  LEFT JOIN zones z ON a.zone_id = z.id
  ORDER BY a.created_at DESC
  LIMIT 200");
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<h2>Alerts (admin)</h2>
<p>Use the controls to set alert status. Changes are recorded immediately.</p>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert info" style="margin-top:8px;"><?= htmlspecialchars($_SESSION['flash']) ?></div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<table class="table" style="margin-top:8px;">
  <thead>
    <tr>
      <th>ID</th>
      <th>Zone</th>
  <th>Title</th>
  <th>Status</th>
  <th>Created</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($alerts as $a): ?>
    <tr>
      <td><?= (int)$a['id'] ?></td>
  <td><?= htmlspecialchars($a['zone_name'] ?? ($a['zone_id'] ?? '')) ?></td>
  <td style="max-width:400px; white-space:normal;"><?= htmlspecialchars($a['title'] ?? '') ?></td>
  <td><?= htmlspecialchars($a['status'] ?? '') ?></td>
  <td><?= htmlspecialchars($a['created_at'] ?? '') ?></td>
      <td>
        <form method="post" action="?page=admin_alert_update_status" style="display:inline-block;">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <select name="status">
            <option value="open" <?= ($a['status']==='open')? 'selected' : '' ?>>open</option>
            <option value="acknowledged" <?= ($a['status']==='acknowledged')? 'selected' : '' ?>>acknowledged</option>
            <option value="closed" <?= ($a['status']==='closed')? 'selected' : '' ?>>closed</option>
          </select>
          <button class="btn" type="submit">Update</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<p style="margin-top:12px;color:#666;">Note: there is no CSRF token on this endpoint yet. Consider adding CSRF protection for production.</p>

<?php
// end
