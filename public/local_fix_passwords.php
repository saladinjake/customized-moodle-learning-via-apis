<?php
/**
 * Headless Password Repair Tool (HTTP-READY)
 * Synchronizes seeded personas with definitive passwords.
 */
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/config.php');

echo "=== LUMINA PASSWORD REPAIR ===\n";
echo "Syncing Vicor personas to definitive credentials...\n\n";

global $DB;
require_once($CFG->dirroot . '/user/lib.php');

$personas = [
    'admin'             => 'Admin1234!',
    'victor_instructor' => 'Victor123!',
    'victor_student'    => 'Victor123!',
    'student_alpha'     => 'Victor123!',
    'student_zeta'      => 'Victor123!',
    'student_omega'     => 'Victor123!',
    'student_theta'     => 'Victor123!',
];

$success_count = 0;
$fail_count = 0;

foreach ($personas as $username => $plaintext) {
    if ($user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
        echo "[MATCH] Updating $username...\n";
        update_internal_user_password($user, $plaintext);
        $success_count++;
    } else {
        echo "[SKIP] User $username not found in database.\n";
        $fail_count++;
    }
}

echo "\nRepair Complete.\n";
echo "Successfully updated: $success_count\n";
echo "Users not found: $fail_count\n";
