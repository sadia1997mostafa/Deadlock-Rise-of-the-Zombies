<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

function out($s) { echo $s . "\n"; }

try {
    out("Checking DB connection and schema...");

    // single-admin config
    out("Configured ADMIN_EMAIL: " . ADMIN_EMAIL);
    $admin = $pdo->prepare("SELECT id,email FROM users WHERE email = ? LIMIT 1");
    $admin->execute([ADMIN_EMAIL]);
    $adminRow = $admin->fetch();
    if ($adminRow) out(" - admin user exists: {$adminRow['email']} (id={$adminRow['id']})");
    else out(" - admin user not found in users table");

    // medical_equipments columns
    $cols = $pdo->query("SHOW COLUMNS FROM medical_equipments")->fetchAll();
    $colNames = array_map(function($c){return $c['Field'];}, $cols);
    out("Medical equipment columns: " . implode(', ', $colNames));

    // quick counts
    $counts = [];
    $tables = ['users','zones','medical_campaign','medical_equipments','safe'];
    foreach ($tables as $t) {
        try { $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(); } catch (Exception $e) { $counts[$t] = 'ERR'; }
    }
    out("Counts:");
    foreach ($counts as $k=>$v) out(" - $k: $v");

    out("\nSearching codebase for 'doctor' mentions (informational):");
    // Simple scan - not exhaustive
    $cmd = 'grep -n "\bdoctor\b" -R . || true';
    // On Windows `grep` may not exist; try PHP glob search instead
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/..'));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $path = $f->getPathname();
        $content = @file_get_contents($path);
        if ($content && preg_match('/\bdoctor\b/i', $content)) {
            $files[] = $path;
        }
    }
    if ($files) {
        foreach ($files as $p) out(" - $p");
    } else {
        out(" - none found");
    }

    out("\nSmoke test complete.");
    exit(0);

} catch (Exception $e) {
    echo "Smoke test failed: " . $e->getMessage() . "\n";
    exit(1);
}
