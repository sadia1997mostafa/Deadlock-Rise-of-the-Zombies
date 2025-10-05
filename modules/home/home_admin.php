<?php
// Admin can access everything (ensure guard for admin users)
ensure_acting_role($pdo, 'admin');
?>
<div class="logo"><span class="dot"></span><h1>Admin Home</h1></div>
<p>Manage the system: approve role requests and oversee missions and resources.</p>

<div class="list">
  <a class="item" href="?page=sa_requests">Manage Role Requests</a>
  <a class="item" href="#">System Settings (future)</a>
</div>

<div class="badge" style="margin-top:12px">Preview other role homes</div>
<div class="list">
  <a class="item" href="?page=home&as=mission_commander">Mission Commander Home</a>
  <a class="item" href="?page=home&as=viewer">Viewer Home (public)</a>
</div>

<hr>
<div class="row">
  <a class="btn alt" href="?page=role_select">Switch Role</a>
  <a class="btn" href="?page=logout">Logout</a>
</div>