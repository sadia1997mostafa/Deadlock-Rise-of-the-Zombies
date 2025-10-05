<?php
require_login();
$acting = get_acting_role();
?>
<div class="logo"><span class="dot"></span><h1>Dashboard</h1></div>
<div class="badge">You are acting as: <b><?= htmlspecialchars($acting) ?></b></div>
<p style="margin-top:10px">Placeholder dashboard. Access flow only (as requested).</p>

<?php if ($acting === 'admin'): ?>
  <div class="list" style="margin-top:16px">
    <a class="item" href="/zom/public/?page=sa_requests">Manage Role Requests</a>
  </div>
<?php endif; ?>

<hr>
<div class="row">
  <a class="btn alt" style="text-align:center" href="/zom/public/?page=role_select">Switch Role</a>
  <a class="btn" style="text-align:center" href="/zom/public/?page=logout">Logout</a>
</div>
