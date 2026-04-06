<?php
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/config.php');
global $DB;

$courseid = 500;
$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course) {
    echo "Course 500 not found.\n";
    exit;
}

echo "Course: " . $course->fullname . " (ID: $courseid)\n";

$sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
echo "Sections count: " . count($sections) . "\n";
foreach ($sections as $s) {
    echo "Section $s->section (ID: $s->id): $s->name [Sequence: $s->sequence]\n";
}

$cms = $DB->get_records('course_modules', ['course' => $courseid]);
echo "Course Modules count: " . count($cms) . "\n";
foreach ($cms as $cm) {
    $mod = $DB->get_record('modules', ['id' => $cm->module]);
    echo "  CMID: $cm->id, Module: " . ($mod ? $mod->name : 'unknown') . ", Instance: $cm->instance, SectionID: $cm->section\n";
}
