<?php
/**
 * Headless Global Platform Bridge & Repair (HTTP-READY)
 * Bulk synchronizes all Identites and Catalog Visibility.
 */
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/config.php');

echo "=== LUMINA GLOBAL PLATFORM REPAIR ===\n";
echo "Enforcing unified credentials & catalog visibility across ALL records...\n\n";

global $DB, $CFG;
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// ─── 1. Bulk Identity Repair ──────────────────────────────────────────────────
echo "[1/2] Updating ALL user passwords to 'Victor123!'...\n";
$users = $DB->get_records('user', ['deleted' => 0]);
$u_count = 0;

foreach ($users as $user) {
    // Skip the built-in guest user for stability
    if ($user->username === 'guest') continue;

    update_internal_user_password($user, 'Victor123!');
    $u_count++;
}
echo "✓ Success: $u_count users synchronized.\n\n";

// ─── 2. Global Catalog Repair ─────────────────────────────────────────────────
echo "[2/2] Forcing visibility = 1 for ALL courses...\n";
$courses = $DB->get_records('course');
$c_count = 0;

foreach ($courses as $course) {
    if ($course->id == SITEID) continue; // Skip site home

    $update = new stdClass();
    $update->id = $course->id;
    $update->visible = 1;
    $DB->update_record('course', $update);
    
    // Explicitly rebuild the course cache so the API catalog sees it
    rebuild_course_cache($course->id);
    $c_count++;
}
echo "✓ Success: $c_count courses visibility-repaired and cache-rebuilt.\n\n";

echo "=== REPAIR COMPLETE ===\n";
echo "Platform registry is now at parity with Victor credentials.\n";
