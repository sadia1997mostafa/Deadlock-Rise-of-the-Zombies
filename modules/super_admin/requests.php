<?php
require_login();
ensure_acting_role($pdo, 'super_admin');

$sql = "SELECT rr.id as req_id, u.id as user_id, u.name, u.email, r.role_name, r.role_key, rr.requested_at
        FROM role_requests rr
        JOIN users u ON rr.user_id = u.id
        JOIN roles r ON rr.role_id = r.id
        WHERE rr.status = 'pending'
        ORDER BY rr.requested_at ASC";
$reqs = $pdo->query($sql)->fetchAll();
?>
<div class="logo"><span class="dot"></span><h1>Pending Role Requests</h1></div>
<?php if (!$reqs): ?>
  <div class="alert">No pending requests.</div>
<?php else: ?>
  <?php foreach($reqs as $r): ?>
    <div class="alert" style="margin-bottom:10px">
      <b><?= htmlspecialchars($r['name']) ?></b> (<?= htmlspecialchars($r['email']) ?>)
      requested role <b><?= htmlspecialchars($r['role_name']) ?></b>
      <br><small class="muted">Requested at: <?= htmlspecialchars($r['requested_at']) ?></small>
      <form class="row" method="post" action="/zom/modules/super_admin/handle_request.php" style="margin-top:8px">
        <input type="hidden" name="req_id" value="<?= (int)$r['req_id'] ?>">
        <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
        <input type="hidden" name="role_key" value="<?= htmlspecialchars($r['role_key']) ?>">
        <input name="comment" placeholder="Optional comment">
        <button class="btn" name="action" value="approve">Approve</button>
        <button class="btn alt" name="action" value="reject">Reject</button>
      </form>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<hr>
<a href="/zom/public/?page=dashboard">Back</a>
