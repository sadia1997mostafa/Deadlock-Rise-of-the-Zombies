<?php
// modules/admin/ambulance_requests.php
// SQL manifest (keys / inline):
// - modules/tools/sql_examples.php: 'ambulance_update_assign', 'ambulance_update_complete', 'ambulance_update_reject'
// - inline SELECT used: SELECT ar.*, u.name AS user_name, z.name AS zone_name FROM ambulance_requests ar JOIN users u ...
require_once __DIR__ . '/../../config/auth.php';
require_login(); if (!is_admin($pdo, current_user_id())) { echo "<p>Admin only</p>"; exit; }

$rows = $pdo->query("SELECT ar.*, u.name AS user_name, z.name AS zone_name FROM ambulance_requests ar JOIN users u ON ar.user_id=u.id LEFT JOIN zones z ON ar.zone_id=z.id WHERE ar.status IN ('requested','assigned') ORDER BY ar.created_at")->fetchAll();
?>
<h2>Ambulance Requests</h2>
<table class="table"><thead><tr><th>User</th><th>Zone</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r['user_name']) ?></td>
    <td><?= htmlspecialchars($r['zone_name']) ?></td>
    <td><?= htmlspecialchars($r['status']) ?></td>
    <td>
      <form method="post" action="?page=admin_ambulance_handle">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
  <button name="action" value="assign" class="btn">Assign</button>
  <button name="action" value="complete" class="btn alt">Complete</button>
  <button name="action" value="reject" class="btn danger">Reject</button>
      </form>
    </td>
  </tr>
<?php endforeach; ?></tbody></table>
