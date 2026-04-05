<?php
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/course/lib.php');

global $DB, $CFG, $PAGE;

// Initialize $PAGE to prevent "get_navigation_overflow_state on null" in web context
$PAGE->set_url(new moodle_url('/local_run_seed.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin'); 

echo "Initiating Enterprise Category Provisioning: 30 Master Specialties...\n";


$categories = [
    'Software Architecture',
    'Machine Learning Ops',
    'Quantum Electrodynamics',
    'Cybersecurity Audit',
    'Financial Modeling',
    'Digital Transformation',
    'UX/UI Research',
    'Aerospace Systems',
    'Biomedical Engineering',
    'Blockchain Infrastructure',
    'Cloud Systems Core',
    'Data Science Mastery',
    'Strategy & Leadership',
    'Global Economics',
    'Creative Direction',
    'Ethics in AI',
    'Behavioral Psychology',
    'Supply Chain Logic',
    'Embedded Systems',
    'Neural Networks',
    'DevOps Engineering',
    'RegTech Solutions',
    'Green Energy Tech',
    'Robotic Process Automation',
    'AR/VR Environments',
    'Legal Compliance',
    'Market Research',
    'Human Capital Mgmt',
    'High-Performance Computing',
    'Digital Forensic Audit'
];

$successCount = 0;

$transaction = $DB->start_delegated_transaction();

try {
    foreach ($categories as $name) {
        // Check if exists
        if ($DB->record_exists('course_categories', ['name' => $name])) {
            echo "Skipping existing category: $name\n";
            continue;
        }

        $cat = new \stdClass();
        $cat->name = $name;
        $cat->description = "Registry node for $name academic curriculum.";
        $cat->parent = 0; 
        
        $newcat = \core_course_category::create($cat);
        if ($newcat) {
            $successCount++;
            echo "Provisioned: $name (id: {$newcat->id})\n";
        }
    }
    $transaction->allow_commit();
    echo "\nSeeding Complete. anchored $successCount new categories into the Moodle registry.\n";
} catch (\Exception $e) {
    $transaction->rollback($e);
    echo "\nFATAL: " . $e->getMessage() . "\n";
}
