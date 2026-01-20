<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../lib/domain.php';
require_once __DIR__ . '/../lib/metrics.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';


$page = $_GET['page'] ?? 'login';


$routes = [
    // authentication
    'login'        => __DIR__ . '/../modules/auth/login.php',
  'signup'       => __DIR__ . '/../modules/auth/signup.php',

    
    'home'         => __DIR__ . '/../modules/home/home_router.php',

 

    // super admin area (role-request pages removed)
  // campaigns
  'campaigns'      => __DIR__ . '/../modules/campaigns/list.php',
  'campaign_create'=> __DIR__ . '/../modules/campaigns/create.php',
  'campaign_view'  => __DIR__ . '/../modules/campaigns/view.php',

  // user panel
  'user_panel'     => __DIR__ . '/../modules/user/panel.php',
  'user_sql_runner' => __DIR__ . '/../modules/user/sql_runner.php',

  // user requests (rendered in layout)
  'ambulance_request' => __DIR__ . '/../modules/requests/ambulance_request.php',
  'icu_request'       => __DIR__ . '/../modules/requests/icu_request.php',

    // admin tools
    'admin_zones'   => __DIR__ . '/../modules/admin/zones.php',
    'admin_vaccines'=> __DIR__ . '/../modules/admin/vaccines.php',
  'sql_examples'  => __DIR__ . '/../modules/admin/admin_sql.php',
  'admin_alerts'  => __DIR__ . '/../modules/admin/alerts.php',
  
  'admin_ambulance_requests' => __DIR__ . '/../modules/admin/ambulance_requests.php',
  'admin_sql_runner' => __DIR__ . '/../modules/admin/sql_runner.php',
  'admin_icu_requests' => __DIR__ . '/../modules/admin/icu_requests.php',
    

];


$raw_actions = [
    'logout'       => __DIR__ . '/../modules/auth/logout.php',
     'region_save' => __DIR__ . '/../modules/world/region_save.php',
  'zone_save'   => __DIR__ . '/../modules/world/zone_save.php',
  'event_save'  => __DIR__ . '/../modules/infection/event_save.php',
  'alert_ack'   => __DIR__ . '/../modules/alerts/ack.php',
  'alert_close' => __DIR__ . '/../modules/alerts/close.php',
  'admin_alert_update_status' => __DIR__ . '/../modules/admin/alert_update_status.php',
  
  'admin_mark_infected' => __DIR__ . '/../modules/admin/mark_infected.php',
  'admin_update_infected_status' => __DIR__ . '/../modules/admin/update_infected_status.php',
 
  'admin_ambulance_handle' => __DIR__ . '/../modules/admin/ambulance_handle.php',
  'admin_icu_handle' => __DIR__ . '/../modules/admin/icu_handle.php',

  // user action endpoints
  'user_cancel_request' => __DIR__ . '/../modules/user/cancel_request.php',
  'user_auto_register'  => __DIR__ . '/../modules/user/auto_register.php',
  'user_export_requests'=> __DIR__ . '/../modules/user/export_requests.php',
  'user_bulk_register'  => __DIR__ . '/../modules/user/bulk_register.php',
  'self_status_action'  => __DIR__ . '/../modules/home/self_status_action.php',

  
  // role request handlers removed


  

];


if (isset($raw_actions[$page])) {
    require $raw_actions[$page];
    exit;
}


$view = $routes[$page] ?? $routes['login'];


ob_start();
require $view;           
$content = ob_get_clean();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Deadlock – Virus Outbreak</title>
  <?php $cssv = file_exists(__DIR__.'/assets/css/style.css') ? filemtime(__DIR__.'/assets/css/style.css') : time(); ?>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= $cssv ?>">
  <style>
    .site-full, .card, .card-like, .container { width: 100% !important; max-width: none !important; margin: 0 !important; padding-left: 24px !important; padding-right: 24px !important; }
    .table { width: 100% !important; }
    .table .btn { width: auto !important; display:inline-block !important; }
  </style>
</head>
<body>

  <div class="site-full">
    <?= $content ?>
  </div>
</body>
</html>
