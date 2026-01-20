<?php
// modules/tools/sql_runner.php
// Run: php modules\tools\sql_runner.php [--exec] [--exec-ddl]
// By default this script will read all files under sql/queries and
// - print each SQL statement
// - execute SELECT statements and print results
// - skip INSERT/UPDATE/DELETE/DDL unless --exec or --exec-ddl is given

require_once __DIR__ . '/../../config/db.php';
if (! isset($pdo) || ! $pdo instanceof PDO) {
    echo "DB connection not available via config/db.php\n";
    exit(1);
}

$argv = $_SERVER['argv'] ?? [];
$execWrites = in_array('--exec', $argv, true);
$execDDL = in_array('--exec-ddl', $argv, true);

function is_select_statement(string $sql): bool {
    $s = ltrim($sql);
    return stripos($s, 'SELECT') === 0 || stripos($s, 'WITH') === 0 || stripos($s, 'SHOW') === 0;
}

function process_file(PDO $pdo, string $file, bool $execWrites, bool $execDDL) {
    echo "\n=== FILE: $file ===\n";
    $content = file_get_contents($file);
    if ($content === false) { echo "(failed to read)\n"; return; }
    // Split statements by semicolon. Use a broad split to catch semicolons followed by any whitespace/newline.
    // This is still naive but sufficient for our example SQL files.
    $parts = preg_split('/;\s*(\r\n|\n|$)/', $content);
    foreach ($parts as $stmt) {
        $stmt = trim($stmt);
    // strip BOM and trim
    $stmt = preg_replace('/^\xEF\xBB\xBF/', '', $stmt);
    $stmt = trim($stmt);
    if ($stmt === '') continue;
    // skip pure-line comments
    if (preg_match('/^\s*--/', $stmt)) continue;
        echo "\n--- SQL Statement ---\n" . substr($stmt,0,1000) . (strlen($stmt)>1000?"...\n":"\n");
    if (is_select_statement($stmt)) {
            try {
                $st = $pdo->prepare($stmt);
                $st->execute();
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                if ($rows) {
                    foreach ($rows as $r) print_r($r);
                } else {
                    echo "(no rows)\n";
                }
            } catch (PDOException $e) {
                echo "SELECT failed: " . $e->getMessage() . "\n";
            }
        } else {
            // Non-SELECT: DDL or write
            $lc = strtolower(ltrim($stmt));
            if (preg_match('/^(create|alter|drop|truncate|rename)\b/', $lc)) {
                echo "(DDL statement) ";
                if ($execDDL) {
                    try { $pdo->exec($stmt); echo "-- DDL executed\n"; } catch (PDOException $e) { echo "DDL failed: " . $e->getMessage() . "\n"; }
                } else {
                    echo "(skipped - use --exec-ddl to execute)\n";
                }
            } else {
                // likely INSERT/UPDATE/DELETE/other
                echo "(write statement) ";
                if ($execWrites) {
                    try { $count = $pdo->exec($stmt); echo "-- executed, affected: $count\n"; } catch (PDOException $e) { echo "Write failed: " . $e->getMessage() . "\n"; }
                } else {
                    echo "(skipped - use --exec to execute writes)\n";
                }
            }
        }
    }
}

$dir = __DIR__ . '/../../sql/queries';
$files = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
if (! $files) { echo "No SQL files found in sql/queries\n"; exit(0); }

echo "SQL Runner - dry run by default. Use --exec to run writes, --exec-ddl to run DDL.\n";
foreach ($files as $f) process_file($pdo, $f, $execWrites, $execDDL);

echo "\nDone.\n";

?>
