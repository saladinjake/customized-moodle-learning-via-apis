<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/course/lib.php');

$courseid = 2;
echo "--- DIAGNOSTIC FOR COURSE $courseid ---\n";

$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course) {
    die("Course $courseid not found.\n");
}

$sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
echo "Sections found: " . count($sections) . "\n";

foreach ($sections as $s) {
    echo "Section #{$s->section} (ID: {$s->id}): Name='{$s->name}' Sequence='{$s->sequence}'\n";
    if (!empty($s->sequence)) {
        $cmids = explode(',', $s->sequence);
        foreach ($cmids as $cmid) {
            $cm = $DB->get_record('course_modules', ['id' => $cmid]);
            if ($cm) {
                $mod = $DB->get_record('modules', ['id' => $cm->module]);
                echo "  - CMID $cmid: Type=" . ($mod ? $mod->name : 'UNKNOWN') . " SectionID={$cm->section} Visible={$cm->visible}\n";
            } else {
                echo "  - CMID $cmid: NOT FOUND in course_modules table!\n";
            }
        }
    }
}

echo "--- END DIAGNOSTIC ---\n";
