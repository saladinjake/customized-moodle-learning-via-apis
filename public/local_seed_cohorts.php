<?php
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/cohort/lib.php');

global $DB, $CFG, $PAGE;

// Initialize $PAGE to prevent "get_navigation_overflow_state on null" in web context
$PAGE->set_url(new moodle_url('/local_run_seed.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin'); 


$syscontext = context_system::instance();

$cohorts = [
    ['name' => '2026 Spring Engineering', 'idnumber' => 'ENG-2026-SP', 'description' => 'Students enrolled in the Spring Engineering track.'],
    ['name' => 'Global Leadership Network', 'idnumber' => 'GLN-MASTER', 'description' => 'Elite leadership development participants.'],
    ['name' => 'Design Foundations Beta', 'idnumber' => 'DES-FND-B1', 'description' => 'Initial beta testers for the design foundational curriculum.']
];

$count = 0;
foreach ($cohorts as $cdata) {
    if (!$DB->record_exists('cohort', ['idnumber' => $cdata['idnumber']])) {
        $cohort = new stdClass();
        $cohort->name = $cdata['name'];
        $cohort->idnumber = $cdata['idnumber'];
        $cohort->description = $cdata['description'];
        $cohort->contextid = $syscontext->id;
        $cohort->component = '';
        $cohort->timecreated = time();
        $cohort->timemodified = time();
        cohort_add_cohort($cohort);
        echo "Created cohort: " . $cdata['name'] . "\n";
        $count++;
    }
}
echo "Seeding complete. Added $count cohorts.\n";
