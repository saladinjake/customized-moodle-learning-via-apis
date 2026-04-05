<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

global $DB;
try {
    echo "Attempting to fetch users...\n";
    $users = $DB->get_records('user', ['deleted' => 0], 'username ASC', 'id, username, email, firstname, lastname, profileimageurl');
    echo "Found " . count($users) . " users.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
