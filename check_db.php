<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
global $DB;

try {
    $users = $DB->get_records('user', null, '', 'id, username');
    echo "SUCCESS: Found " . count($users) . " users.\n";
    foreach ($users as $user) {
        echo " - " . $user->username . " (ID: " . $user->id . ")\n";
    }
} catch (Exception $e) {
    echo "FAILURE: " . $e->getMessage() . "\n";
}
