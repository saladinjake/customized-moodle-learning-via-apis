<?php
require_once(__DIR__ . '/config.php');
/**
 * Headless Engagement Seeder (Grades & Messages Entrypoint)
 * HTTP-READY / SESSION-FREE
 */
// // define('CLI_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/message/lib.php');
require_once($CFG->dirroot . '/lib/gradelib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

global $DB, $CFG, $PAGE;

// Initialize $PAGE to prevent "get_navigation_overflow_state on null" in web context
$PAGE->set_url(new moodle_url('/local_run_seed.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin'); 

echo "Initiating Grade and Message Seeding for Victor...\n";


// Defensive: ensure Victor personas exist even if seed_moodle failed mid-run
foreach (['victor_student' => 'Student', 'victor_instructor' => 'Instructor'] as $uname => $lname) {
    if (!$DB->record_exists('user', ['username' => $uname])) {
        echo "[Fallback] Creating missing user: $uname\n";
        $u = new stdClass();
        $u->username  = $uname;
        $u->email     = "$uname@lumina.example.com";
        $u->firstname = 'Victor';
        $u->lastname  = $lname;
        $u->confirmed = 1;
        $u->lang = 'en';
        $u->timezone = '99';
        $u->calendartype = 'gregorian';
        $u->maildisplay = 1;
        $u->mailformat = 1;
        $u->maildigest = 0;
        $u->autosubscribe = 1;
        $u->trackforums = 0;
        $u->mnethostid = $CFG->mnet_localhost_id ?? 1;
        // Mandatory Moodle 5.x schema fields
        $u->city              = '';
        $u->country           = '';
        $u->description       = '';
        $u->descriptionformat = FORMAT_HTML;
        $u->picture           = 0;
        $u->idnumber          = '';
        $u->institution       = '';
        $u->department        = '';
        $u->phone1            = '';
        $u->phone2            = '';
        $u->address           = '';
        $u->firstnamephonetic = '';
        $u->lastnamephonetic  = '';
        $u->middlename        = '';
        $u->alternatename     = '';
        user_create_user($u, false, false);
    }
}

$transaction = $DB->start_delegated_transaction();

try {
    // 1. Get Users (guaranteed to exist due to defensive block above)
    $victor_student    = $DB->get_record('user', ['username' => 'victor_student'],    '*', MUST_EXIST);
    $victor_instructor = $DB->get_record('user', ['username' => 'victor_instructor'], '*', MUST_EXIST);
    $admin = get_admin();

    // 2. Seed Messages (Conversations)
    echo "Seeding Messages...\n";
    
    $message_data = [
        ['from' => $admin, 'to' => $victor_student, 'msg' => 'Identity migration to modern registry complete. Neutral link stabilized. Deployment sequence validated across all nodes.'],
        ['from' => $victor_instructor, 'to' => $victor_student, 'msg' => 'Your credentials have been verified for Phase 3 asset access. Please review the security protocols at your earliest convenience.']
    ];

    foreach ($message_data as $data) {
        try {
            $conv = \core_message\api::get_conversation_between_users([$data['from']->id, $data['to']->id]);
            if (!$conv) {
                $conv = \core_message\api::create_conversation(
                    \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL,
                    [$data['from']->id, $data['to']->id]
                );
            }
            // Ensure members are in it (mostly defensive for corrupt legacy data)
            if (!\core_message\api::is_user_in_conversation($data['from']->id, $conv->id)) {
                 \core_message\api::add_members_to_conversation([$data['from']->id], $conv->id);
            }
            if (!\core_message\api::is_user_in_conversation($data['to']->id, $conv->id)) {
                 \core_message\api::add_members_to_conversation([$data['to']->id], $conv->id);
            }

            \core_message\api::send_message_to_conversation(
                $data['from']->id,
                $conv->id,
                $data['msg'],
                FORMAT_MOODLE
            );
            echo "Message seeded from {$data['from']->username} to {$data['to']->username}\n";
        } catch (Exception $e) {
            echo "Warning: Messaging failed for {$data['from']->username}: " . $e->getMessage() . "\n";
        }
    }

    // 3. Seed Grades
    echo "Seeding Grades...\n";
    
    $course = $DB->get_record('course', ['shortname' => 'MX-500-1']);
    if (!$course) {
         echo "Warning: No courses found from curriculum seeder. Creating a lightweight mock course for grades.\n";
         $course_data = new stdClass();
         $course_data->fullname = "Enterprise Architecture & Neural Design";
         $course_data->shortname = "MX-GRADE-POC";
         $course_data->category = 1;
         $course_data->format = 'topics';
         $new_course = create_course($course_data);
         $course = $new_course;
    }

    // Create a Grade Item
    $gi = $DB->get_record('grade_items', ['courseid' => $course->id, 'itemname' => 'Final Ledger']);
    if (!$gi) {
        $grade_item = new grade_item([
            'courseid' => $course->id,
            'categoryid' => null,
            'itemname' => 'Final Ledger',
            'itemtype' => 'manual',
            'itemmodule' => '',
            'iteminstance' => 0,
            'grademin' => 0,
            'grademax' => 100
        ]);
        $grade_item->insert();
        $gi = $grade_item;
    } else {
        $gi = new grade_item($gi);
    }

    // Insert Grade for Student
    $gi->update_final_grade($victor_student->id, 92, 'Seeder', 'Seeded via CLI', FORMAT_MOODLE);

    $transaction->allow_commit();
    echo "\nSeeding Complete. Grades and Messages injected successfully.\n";

} catch (\Exception $e) {
    $transaction->rollback($e);
    echo "\nFATAL: " . $e->getMessage() . "\n";
}
