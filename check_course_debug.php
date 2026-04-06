<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/public/config.php');
$courseid = $argv[1] ?? 2;
global $DB;
$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course) die("Course not found\n");
echo "Course: {$course->fullname} (ID: {$course->id})\n";
$sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
foreach ($sections as $s) {
    echo "Section {$s->section} [ID: {$s->id}]: {$s->name} (Sequence: {$s->sequence})\n";
    if (!empty($s->sequence)) {
        $cms = explode(',', $s->sequence);
        foreach ($cms as $cmid) {
            $cm = $DB->get_record_sql("SELECT cm.*, m.name as modname FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module WHERE cm.id = ?", [$cmid]);
            if ($cm) {
                echo "  Module [CMID: {$cm->id}]: {$cm->modname} (Section ID in CM: {$cm->section})\n";
            } else {
                echo "  Module [CMID: $cmid]: NOT FOUND in course_modules\n";
            }
        }
    }
}
