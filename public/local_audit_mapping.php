<?php
require_once(__DIR__ . '/config.php');
global $DB;
header('Content-Type: text/plain');

$courseid = optional_param('courseid', 500, PARAM_INT);
echo "AUDIT FOR COURSE $courseid\n";
echo "========================\n\n";

$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course) {
    die("Course not found\n");
}

echo "Course: " . $course->fullname . " (" . $course->shortname . ")\n\n";

$sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
echo "SECTIONS found: " . count($sections) . "\n";
foreach ($sections as $s) {
    echo " - Section #{$s->section} [ID: {$s->id}] Name: '{$s->name}' Sequence: '{$s->sequence}'\n";
}

echo "\nMODULES (via course_modules table) found:\n";
$cms = $DB->get_records('course_modules', ['course' => $courseid]);
echo "Count: " . count($cms) . "\n";
foreach ($cms as $cm) {
    $modname = $DB->get_field('modules', 'name', ['id' => $cm->module]);
    $inst_name = $DB->get_field($modname, 'name', ['id' => $cm->instance]);
    echo " - CM ID: {$cm->id} | Type: $modname | Instance Name: '$inst_name' | Section ID in DB: {$cm->section}\n";
}

echo "\nSUMMARY:\n";
$unmapped_cms = 0;
foreach ($cms as $cm) {
    if (!isset($sections[$cm->section])) {
        $unmapped_cms++;
    }
}
echo "Unmapped CMs (section ID points to non-existent section for this course): $unmapped_cms\n";
