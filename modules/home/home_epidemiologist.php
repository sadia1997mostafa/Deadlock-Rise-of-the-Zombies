<?php
ensure_acting_role($pdo, 'epidemiologist');
?>
<div class="logo"><span class="dot"></span><h1>Epidemiologist Home</h1></div>
<p>Report infection events and update infection status for survivors and zones.</p>

<div class="list">
  <a class="item" href="#">Log Infection Event (future)</a>
  <a class="item" href="#">Update Zone Heatmap (future)</a>
  <a class="item" href="#">Infection Statistics (future)</a>
</div>

<hr>
<div class="row">
  <a class="btn alt" href="?page=role_select">Switch Role</a>
  <a class="btn" href="?page=logout">Logout</a>
</div>
