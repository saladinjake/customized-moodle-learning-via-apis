<?php
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/config.php');
/**
 * DEFINTIVE CREDENTIAL AUDIT (INTERNAL)
 */
// define('CLI_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

global $DB;

header('Content-Type: text/plain');
echo "=== DEFINITIVE USER REGISTRY AUDIT ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

$users = $DB->get_records_select('user', "deleted = 0 AND suspended = 0 AND username != 'guest'", [], 'id ASC', 'username, firstname, lastname, email');

printf("%-20s | %-30s | %-30s\n", "USERNAME", "FULL NAME", "EMAIL");
echo str_repeat("-", 85) . "\n";

foreach ($users as $u) {
    printf("%-20s | %-30s | %-30s\n", 
        $u->username, 
        $u->firstname . " " . $u->lastname, 
        $u->email
    );
}
echo "\n=== END AUDIT ===\n";
