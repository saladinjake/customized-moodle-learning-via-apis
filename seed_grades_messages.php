<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/public/config.php');

echo "Initiating Grade and Message Seeding for Victor...\n";

global $DB, $CFG;
require_once($CFG->dirroot . '/message/lib.php');
require_once($CFG->dirroot . '/lib/gradelib.php');

$transaction = $DB->start_delegated_transaction();

try {
    // 1. Get Users
    $victor_student = $DB->get_record('user', ['username' => 'victor_student'], '*', MUST_EXIST);
    $victor_instructor = $DB->get_record('user', ['username' => 'victor_instructor'], '*', MUST_EXIST);
    $admin = get_admin();

    // 2. Seed Messages (Conversations)
    echo "Seeding Messages...\n";
    
    // Conversation 1: Admin -> Student
    $conv_id_1 = \core_message\api::create_conversation(
        \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL,
        [$admin->id, $victor_student->id]
    )->id;
    
    \core_message\api::send_message_to_conversation(
        $admin->id,
        $conv_id_1,
        'Identity migration to modern registry complete. Neutral link stabilized. Deployment sequence validated across all nodes.',
        FORMAT_MOODLE
    );

    // Conversation 2: Instructor -> Student
    $conv_id_2 = \core_message\api::create_conversation(
        \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL,
        [$victor_instructor->id, $victor_student->id]
    )->id;

    \core_message\api::send_message_to_conversation(
        $victor_instructor->id,
        $conv_id_2,
        'Your credentials have been verified for Phase 3 asset access. Please review the security protocols at your earliest convenience.',
        FORMAT_MOODLE
    );

    // 3. Seed Grades (We need some courses and grade items first if they don't exist, or just use raw DB inserts for POC purposes)
    echo "Seeding Grades...\n";
    
    // Check if courses exist, if not, wait for curriculum seeder or just mock grade_grades records.
    // Since we want this to work regardless of curriculum state, let's create a fake course and grade item if none exist.
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

    // Insert Grade for Student
    $grade_item->update_final_grade($victor_student->id, 92, 'Seeder', 'Seeded via CLI', FORMAT_MOODLE);

    $transaction->allow_commit();
    echo "\nSeeding Complete. Grades and Messages injected successfully.\n";

} catch (\Exception $e) {
    $transaction->rollback($e);
    echo "\nFATAL: " . $e->getMessage() . "\n";
}
