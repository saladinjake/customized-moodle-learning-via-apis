<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/public/config.php');
require_once($CFG->dirroot . '/user/lib.php');

$emails = ['admin@gmail.com', 'juwavictor1@gmail.com', 'juwavictor2@gmail.com'];
foreach ($emails as $email) {
    if ($user = $DB->get_record('user', ['email' => $email])) {
        // Moodle 3.11+ requires update_internal_user_password
        update_internal_user_password($user, 'Moodle@123');
        // Ensure auth type is manual
        $DB->set_field('user', 'auth', 'manual', ['id' => $user->id]);
        echo "Reset password for " . $email . " to Moodle@123" . PHP_EOL;
    } else {
        echo "User " . $email . " not found" . PHP_EOL;
    }
}
