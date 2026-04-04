<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/public/config.php');

$user = $DB->get_record('user', ['username' => 'victor_student']);

// Test Messages
require_once($CFG->dirroot . '/message/lib.php');
$conversations = \core_message\api::get_conversations($user->id);
print_r($conversations);

// Test Grades
$sql = "SELECT gg.id, gg.itemid, gg.finalgrade, gi.courseid, c.fullname 
        FROM {grade_grades} gg 
        JOIN {grade_items} gi ON gg.itemid = gi.id 
        JOIN {course} c ON gi.courseid = c.id 
        WHERE gg.userid = ?";
$grades = $DB->get_records_sql($sql, [$user->id]);
print_r($grades);
