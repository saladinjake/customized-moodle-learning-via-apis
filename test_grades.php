<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/public/config.php');

$user = $DB->get_record('user', ['username' => 'victor_student']);
$result = external_api::call_external_function('gradereport_user_get_grades_table', ['userid' => $user->id], true);
print_r($result);
