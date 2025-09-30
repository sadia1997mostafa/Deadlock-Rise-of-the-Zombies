<?php
ensure_acting_role($pdo, 'data_clerk');
?>
<div class="logo"><span class="dot"></span><h1>Data Clerk Home</h1></div>
<p>Bulk-create records like survivor lists and zone data from files or forms.</p>

<div class="list">
  <a class="item" href="#">Upload Bulk Data (future)</a>
  <a class="item" href="#">Batch Edit Records (future)</a>
</div>

<hr>
<div class="row">
  <a class="btn alt" href="?page=role_select">Switch Role</a>
  <a class="btn" href="?page=logout">Logout</a>
</div>
