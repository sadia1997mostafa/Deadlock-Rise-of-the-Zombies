<?php
// modules/admin/icu_requests.php
require_once __DIR__ . '/../../config/auth.php';
require_login(); if (!is_admin($pdo, current_user_id())) { echo "<p>Admin only</p>"; exit; }

$rows = $pdo->query("SELECT ir.*, u.name AS user_name, z.name AS zone_name FROM icu_requests ir JOIN users u ON ir.user_id=u.id LEFT JOIN zones z ON ir.zone_id=z.id WHERE ir.status IN ('requested','confirmed','admitted') ORDER BY ir.created_at")->fetchAll();
?>
<h2>ICU Requests</h2>
<table class="table"><thead><tr><th>User</th><th>Zone</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r['user_name']) ?></td>
    <td><?= htmlspecialchars($r['zone_name']) ?></td>
    <td><?= htmlspecialchars($r['status']) ?></td>
    <td>
      <form method="post" action="?page=admin_icu_handle">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
  <button name="action" value="confirm" class="btn">Confirm</button>
  <button name="action" value="admit" class="btn alt">Admit</button>
  <button name="action" value="reject" class="btn danger">Reject</button>
      </form>
    </td>
  </tr>
<?php endforeach; ?></tbody></table>
