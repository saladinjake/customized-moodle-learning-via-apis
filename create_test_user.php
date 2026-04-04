<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/public/config.php');
require_once($CFG->dirroot . '/user/lib.php');

global $DB, $CFG;

$username = 'lone_student';
$email = 'lone@student.com';
$password = 'Moodle@123';

if ($DB->record_exists('user', ['username' => $username])) {
    echo "User $username already exists. Deleting to recreate...\n";
    $user = $DB->get_record('user', ['username' => $username]);
    delete_user($user);
}

$user = new stdClass();
$user->username = $username;
$user->email = $email;
$user->firstname = 'Lone';
$user->lastname = 'Student';
$user->confirmed = 1;
$user->auth = 'manual';
$user->mnethostid = $CFG->mnet_localhost_id;

$userid = user_create_user($user);

if ($userid) {
    $user_obj = $DB->get_record('user', ['id' => $userid]);
    update_internal_user_password($user_obj, $password);
    echo "SUCCESS: Created user '$username' with no enrollments.\n";
    echo "Login Credentials:\n";
    echo "Username: $username\n";
    echo "Password: $password\n";
} else {
    echo "ERROR: Failed to create user.\n";
}
