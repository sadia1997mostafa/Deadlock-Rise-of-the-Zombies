<?php
// PUBLIC viewer page (no require_login/ensure_acting_role)
$logged = is_logged_in();
?>
<div class="logo"><span class="dot"></span><h1>Viewer Home</h1></div>
<p>Read-only access to lists & analytics (public demo).</p>

<div class="list">
  <a class="item" href="#">Read-only Dashboards (later)</a>
</div>

<hr>
<?php if (!$logged): ?>
  <div class="row">
    <a class="btn" href="?page=login">Login</a>
    <a class="btn alt" href="?page=signup">Sign up</a>
  </div>
<?php else: ?>
  <div class="row">
    <a class="btn alt" href="?page=role_select">Switch Role</a>
    <a class="btn" href="?page=logout">Logout</a>
  </div>
<?php endif; ?>
