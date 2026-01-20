<?php
// modules/user/panel.php
// Simple user panel that exposes the user-facing query functions.

require_once __DIR__ . '/../../lib/user_sql_functions.php';

// require logged-in user for the panel
if (!function_exists('is_logged_in') || !is_logged_in()) {
    echo '<div class="card-like"><h2>Please log in to view your panel</h2><p><a class="btn" href="?page=login">Login</a></p></div>';
    return;
}

$uid = current_user_id();

// show flash messages from redirects (auto_register / cancel_request / bulk)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['flash'])) {
    echo '<div class="card-like" style="background:#0b1220;color:#d1ffd6;margin-bottom:8px;">' . htmlspecialchars($_SESSION['flash']) . '</div>';
    unset($_SESSION['flash']);
}

// handle registration action from this panel (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_campaign_id'])) {
    $cid = (int)$_POST['register_campaign_id'];
    $res = user_register_for_campaign($pdo, $uid, $cid);
    $msg = $res['message'] ?? 'Done';
    echo '<div class="card-like"><strong>' . htmlspecialchars($msg) . '</strong></div>';
}

// Dashboard: quick links and small widgets calling the helper functions
echo '<div class="logo"><h1>User Panel</h1></div>';

// Quick: My requests (use vw_user_requests view) with filter and collapse behavior
echo '<div class="card-like"><h2>My Requests</h2>';
// request type filter (all / ambulance / icu)
$r_type = isset($_GET['r_type']) ? trim($_GET['r_type']) : 'all';
echo '<form method="get" action="?page=user_panel" style="margin-bottom:8px;display:flex;gap:8px;align-items:center;">';
echo '<input type="hidden" name="page" value="user_panel">';
echo '<label style="font-size:0.9em;margin-right:6px;">Type</label>';
echo '<select name="r_type">';
echo '<option value="all"' . (($r_type==='all')?' selected':'') . '>All</option>';
echo '<option value="ambulance"' . (($r_type==='ambulance')?' selected':'') . '>Ambulance</option>';
echo '<option value="icu"' . (($r_type==='icu')?' selected':'') . '>ICU</option>';
echo '</select>';
echo '<button class="btn" type="submit">Filter</button>';
echo '</form>';

try {
    // using
    $reqs = user_get_my_requests($pdo, $uid, 1, 50, null);
  
    if ($r_type !== 'all') {
        $reqs = array_values(array_filter($reqs, function($r) use ($r_type) {
            return isset($r['type']) && ($r['type'] === $r_type);
        }));
    }
} catch (Throwable $e) {
    echo '<div class="card-like" style="color:darkred"><strong>Error loading requests:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    $reqs = [];
}
if ($reqs) {
    // build option lists for cancel form selects
    $ambulance_options = [];
    $icu_options = [];
    foreach ($reqs as $r) {
        $id = htmlspecialchars((string)($r['request_id'] ?? ''));
        $label = htmlspecialchars('ID ' . ($r['request_id'] ?? '') . ' — ' . ($r['zone_name'] ?? '') . ' @ ' . substr(($r['created_at'] ?? ''),0,16));
        if (isset($r['type']) && strtolower($r['type']) === 'ambulance') $ambulance_options[] = '<option value="' . $id . '">' . $label . '</option>';
        if (isset($r['type']) && strtolower($r['type']) === 'icu') $icu_options[] = '<option value="' . $id . '">' . $label . '</option>';
    }

    echo '<table class="table limit-3" data-section="myRequests"><thead><tr><th>ID</th><th>Type</th><th>Zone</th><th>Created</th><th>Status</th><th>Notes</th></tr></thead><tbody>';
    foreach ($reqs as $i => $r) {
        $detail = $r['details'] ?? '';
        $rowClass = $i >= 3 ? 'collapsed-row' : '';
        $rowStyle = $i >= 3 ? 'style="display:none"' : '';
        echo '<tr class="' . $rowClass . '" ' . $rowStyle . '>';
        echo '<td>' . htmlspecialchars((string)($r['request_id'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars($r['type']) . '</td>';
        echo '<td>' . htmlspecialchars($r['zone_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($r['created_at'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($r['status'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars(substr($detail,0,80)) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else { echo '<p><em>No requests found.</em></p>'; }
echo '</div>';

//  My registrations (use vw_user_registrations view)
echo '<div class="card-like" style="margin-top:12px;"><h2>My Campaign Registrations</h2>';
try {
    $st2 = $pdo->prepare('SELECT registration_id, campaign_id, campaign_name, campaign_created, description, registered_at FROM vw_user_registrations WHERE user_id = ? ORDER BY registered_at DESC LIMIT 20');
    $st2->execute([$uid]);
    $regs = $st2->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo '<div class="card-like" style="color:darkred"><strong>Error loading registrations:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    $regs = [];
}
if ($regs) {
    echo '<table class="table"><thead><tr><th>Campaign</th><th>Created</th><th>Description</th><th>Registered</th></tr></thead><tbody>';
    foreach ($regs as $r) {
        $campaign_name = $r['campaign_name'] ?? '';
        $campaign_created = $r['campaign_created'] ?? '';
        $campaign_desc = $r['description'] ?? '';
        echo '<tr><td>' . htmlspecialchars($campaign_name) . '</td><td>' . htmlspecialchars($campaign_created) . '</td><td>' . htmlspecialchars(substr($campaign_desc,0,80)) . '</td><td>' . htmlspecialchars($r['registered_at'] ?? '') . '</td></tr>';
    }
    echo '</tbody></table>';
} else { echo '<p><em>No registrations yet.</em></p>'; }
echo '</div>';


echo '<div class="card-like" style="margin-top:12px;"><h2>Active Campaigns</h2>';
// Show a single pointer to open the runner which will display the full campaigns table
$runnerAll = '?page=user_sql_runner&fn=user_get_active_campaigns';
echo '<p>To inspect active campaigns in detail use the runner:</p>';
echo '<p><a class="btn" href="' . htmlspecialchars($runnerAll) . '">Open Active Campaigns in Runner</a></p>';
echo '</div>';

// Quick: Campaigns list intentionally removed from user panel (kept in Home view)

// Quick: Equipment availability
echo '<div class="card-like" style="margin-top:12px;"><h2>Equipment Availability</h2>';
try {
    $eq = user_get_equipment_availability($pdo, null, null, 0);
} catch (Throwable $e) {
    echo '<div class="card-like" style="color:darkred"><strong>Error running user_get_equipment_availability:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    $eq = [];
}
if ($eq) {
    echo '<table class="table limit-3"><thead><tr><th>Name</th><th>Total Stock</th><th>Available</th></tr></thead><tbody>';
    foreach ($eq as $i => $r) {
        $rowClass = $i >= 3 ? 'collapsed-row' : '';
        $rowStyle = $i >= 3 ? 'style="display:none"' : '';
        echo '<tr class="' . $rowClass . '" ' . $rowStyle . '>';
        echo '<td>' . htmlspecialchars($r['name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars((string)($r['total_stock'] ?? $r['stock'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($r['available'] ?? $r['stock'] ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else { echo '<p><em>No equipment data.</em></p>'; }
echo '</div>';

// 1) Recent infection events
try {
    $events = user_recent_infection_events($pdo, 7, 20);
} catch (Throwable $e) {
    $events = [];
}
echo '<h3 style="margin-top:6px">Recent Infection Events (7d)</h3>';
if ($events) {
    echo '<table class="table limit-3"><thead><tr><th>Time</th><th>Zone</th><th>Reporter</th><th>Type</th><th>Cases</th></tr></thead><tbody>';
    foreach ($events as $i => $ev) {
        $rowClass = $i >= 3 ? 'collapsed-row' : '';
        $rowStyle = $i >= 3 ? 'style="display:none"' : '';
        echo '<tr class="' . $rowClass . '" ' . $rowStyle . '><td>' . htmlspecialchars($ev['created_at'] ?? '') . '</td><td>' . htmlspecialchars($ev['zone_name'] ?? '') . '</td><td>' . htmlspecialchars($ev['reporter_name'] ?? '') . '</td><td>' . htmlspecialchars($ev['event_type'] ?? '') . '</td><td>' . (int)($ev['cases']??0) . '</td></tr>';
    }
    echo '</tbody></table>';
} else { echo '<p><em>No recent infection events.</em></p>'; }

// 2) Campaigns registration summary (group by / having)
try { $summary = user_campaigns_registration_summary($pdo, 1); } catch (Throwable $e) { $summary = []; }
echo '<h3 style="margin-top:10px">Campaigns Registration Summary</h3>';
if ($summary) {
    echo '<table class="table limit-3"><thead><tr><th>Campaign</th><th>Zone</th><th>Capacity</th><th>Registrations</th><th>Slots Left</th></tr></thead><tbody>';
    foreach ($summary as $i => $s) {
        $rowClass = $i >= 3 ? 'collapsed-row' : '';
        $rowStyle = $i >= 3 ? 'style="display:none"' : '';
        echo '<tr class="' . $rowClass . '" ' . $rowStyle . '><td>' . htmlspecialchars($s['title'] ?? '') . '</td><td>' . htmlspecialchars($s['zone_name'] ?? '') . '</td><td>' . htmlspecialchars((string)$s['capacity']) . '</td><td>' . (int)$s['registrations'] . '</td><td>' . (int)$s['slots_left'] . '</td></tr>';
    }
    echo '</tbody></table>';
} else { echo '<p><em>No campaigns with open slots found.</em></p>'; }

// 3) Campaigns without registrations (NOT EXISTS)
try { $no_regs = user_campaigns_without_registrations_notexists($pdo); } catch (Throwable $e) { $no_regs = []; }
echo '<h3 style="margin-top:10px">Campaigns Without Registrations</h3>';
if ($no_regs) {
    echo '<table class="table limit-3"><thead><tr><th>Campaign</th><th>Zone</th><th>Created</th></tr></thead><tbody>';
    foreach ($no_regs as $i => $c) {
        $rowClass = $i >= 3 ? 'collapsed-row' : '';
        $rowStyle = $i >= 3 ? 'style="display:none"' : '';
        echo '<tr class="' . $rowClass . '" ' . $rowStyle . '><td>' . htmlspecialchars($c['title'] ?? '') . '</td><td>' . htmlspecialchars($c['zone_name'] ?? '') . '</td><td>' . htmlspecialchars($c['created_at'] ?? '') . '</td></tr>';
    }
    echo '</tbody></table>';
} else { echo '<p><em>All campaigns have registrations.</em></p>'; }

// 4) Top zones by case rate
try { $top = user_top_zones_by_case_rate($pdo, 7, 10); } catch (Throwable $e) { $top = []; }
echo '<h3 style="margin-top:10px">Top Zones by Case Rate (per 1k population)</h3>';
if ($top) {
    echo '<table class="table limit-3"><thead><tr><th>Zone</th><th>Population</th><th>Cases</th><th>Cases/1k</th></tr></thead><tbody>';
    foreach ($top as $i => $z) {
        $rowClass = $i >= 3 ? 'collapsed-row' : '';
        $rowStyle = $i >= 3 ? 'style="display:none"' : '';
        echo '<tr class="' . $rowClass . '" ' . $rowStyle . '><td>' . htmlspecialchars($z['zone_name'] ?? '') . '</td><td>' . htmlspecialchars((string)$z['population']) . '</td><td>' . (int)$z['total_cases'] . '</td><td>' . htmlspecialchars(number_format((float)$z['cases_per_1000'],2)) . '</td></tr>';
    }
    echo '</tbody></table>';
} else { echo '<p><em>No zone case data available.</em></p>'; }

// Functional feature 1: Auto-register for best open campaign in your zone
echo '<h3 style="margin-top:10px">Auto-register: Best Campaign In My Zone</h3>';
echo '<p>Automatically register you for the open campaign in your zone with the most slots left.</p>';
echo '<form method="post" action="?page=user_auto_register" style="display:flex;gap:8px;align-items:center;">';
echo '<button class="btn" type="submit" name="auto_register">Auto-register</button>';
echo '</form>';

// Functional feature 2: Cancel a request (ambulance / icu)
echo '<h3 style="margin-top:10px">Cancel a Request</h3>';
echo '<p>Cancel one of your ambulance or ICU requests by selecting its ID from the dropdown.</p>';
echo '<form method="post" action="?page=user_cancel_request" style="display:flex;gap:8px;align-items:center;">';
echo '<select name="req_type"><option value="ambulance">Ambulance</option><option value="icu">ICU</option></select>';
// prepare HTML for selects (fall back to empty lists if not prepared)
$ambulance_html = isset($ambulance_options) ? implode('', $ambulance_options) : '';
$icu_html = isset($icu_options) ? implode('', $icu_options) : '';
echo '<select id="req_id_ambulance" name="req_id" style="display:none;min-width:220px;" disabled>' . $ambulance_html . '</select>';
echo '<select id="req_id_icu" name="req_id" style="display:none;min-width:220px;" disabled>' . $icu_html . '</select>';
echo '<button class="btn" type="submit">Cancel Request</button>';
echo '</form>';

// JS: toggle which request-id select is enabled based on chosen type
$cancel_js = <<<'JS'
<script>(function(){
    var form = document.querySelector('form[action="?page=user_cancel_request"]');
    if (!form) return;
    var typeSel = form.querySelector('select[name="req_type"]');
    var amb = document.getElementById('req_id_ambulance');
    var icu = document.getElementById('req_id_icu');
    function update(){
        var v = typeSel.value;
        if (v === 'ambulance'){
            if (amb){ amb.style.display = ''; amb.disabled = false; }
            if (icu){ icu.style.display = 'none'; icu.disabled = true; }
        } else {
            if (amb){ amb.style.display = 'none'; amb.disabled = true; }
            if (icu){ icu.style.display = ''; icu.disabled = false; }
        }
    }
    typeSel.addEventListener('change', update);
    update();
})();</script>
JS;
echo $cancel_js;

echo '</div>';

// Handle the simple bulk-register POST coming back to this panel route (compat)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['campaign_ids_text'])) {
    $text = trim($_POST['campaign_ids_text'] ?? '');
    $ids = array_filter(array_map('intval', array_map('trim', explode(',', $text))));
    if ($ids) {
        // call the bulk endpoint internally
        $results = [];
        foreach ($ids as $cid) { $r = user_register_for_campaign($pdo, $uid, $cid); $results[] = ['campaign'=>$cid,'ok'=>!empty($r['ok']),'message'=>$r['message'] ?? '']; }
        echo '<div class="card-like" style="margin-top:8px;"><strong>Bulk register results:</strong><ul>';
        foreach ($results as $res) echo '<li>Campaign ' . htmlspecialchars((string)$res['campaign']) . ': ' . htmlspecialchars($res['message']) . '</li>';
        echo '</ul></div>';
    } else {
        echo '<div class="card-like" style="margin-top:8px;color:darkred"><strong>No valid campaign ids provided.</strong></div>';
    }
}

// Initialize limit-3 behavior on this page (similar to home_viewer)
$js = <<<'JS'
<script>(function(){
    function initLimit3(root){
        root = root || document;
        var tables = root.querySelectorAll("table.limit-3");
        tables.forEach(function(table){
            if (table.__limit3_init) return;
            table.__limit3_init = true;
            var hiddenRows = table.querySelectorAll('tbody tr.collapsed-row');
            if (!hiddenRows || hiddenRows.length === 0) return;
            var btn = document.createElement('button');
            btn.className = 'btn see-more';
            btn.type = 'button';
            btn.setAttribute('aria-expanded','false');
            btn.textContent = 'See more (' + hiddenRows.length + ')';
            table.parentNode.insertBefore(btn, table.nextSibling);
            btn.addEventListener('click', function(){
                var expanded = btn.getAttribute('aria-expanded') === 'true';
                if (!expanded) {
                    hiddenRows.forEach(function(r){ r.style.display = ''; });
                    btn.textContent = 'Show less';
                    btn.setAttribute('aria-expanded','true');
                    setTimeout(function(){ table.scrollIntoView({behavior:'smooth'}); }, 50);
                } else {
                    hiddenRows.forEach(function(r){ r.style.display = 'none'; });
                    btn.textContent = 'See more (' + hiddenRows.length + ')';
                    btn.setAttribute('aria-expanded','false');
                    setTimeout(function(){ table.scrollIntoView({behavior:'smooth'}); }, 50);
                }
            });
        });
    }
    initLimit3(document);
})();</script>
JS;
echo $js;
