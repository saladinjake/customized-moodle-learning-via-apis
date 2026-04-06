<?php
define('CLI_SCRIPT', false);
require(__DIR__ . '/config.php');
global $DB;

header('Content-Type: text/plain');
echo "=== LUMINA DATABASE AUDIT ===\n";

try {
    // 1. Connection Check
    $dbtype = $DB->get_dbfamily();
    echo "DB Family: $dbtype\n";

    // 2. Table Audit
    echo "\nScanning for tables...\n";
    $tables = $DB->get_tables();
    $mdl_tables = array_filter($tables, function($t) { return strpos($t, 'course') !== false || strpos($t, 'modules') !== false; });
    echo "Found " . count($tables) . " total tables.\n";
    echo "Relevant tables: " . implode(', ', $mdl_tables) . "\n";

    // 3. Raw Module Check
    echo "\nQuerying mdl_modules directly...\n";
    $count = $DB->count_records('modules');
    echo "Count records (API): $count\n";

    $raw = $DB->get_record_sql("SELECT COUNT(*) as c FROM {modules}");
    echo "Count records (Raw SQL): " . ($raw ? $raw->c : 'FAIL') . "\n";

    // 4. List all modules
    echo "\nListing all modules ID/Name:\n";
    $modules = $DB->get_records('modules', [], '', 'id, name');
    foreach ($modules as $m) {
        echo " - [{$m->id}] {$m->name}\n";
    }

    // 5. Schema check
    $schema = $DB->get_record_sql("SELECT current_schema()");
    echo "\nCurrent Schema: " . ($schema ? $schema->current_schema : 'Unknown') . "\n";

} catch (Exception $e) {
    echo "AUDIT EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
