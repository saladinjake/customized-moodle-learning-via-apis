<?php
// Rapid End-to-End Headless Moodle Matrix Integration Test
define('CLI_SCRIPT', true);
require('config.php');
require_once('course/lib.php');

global $DB, $USER;
$admin = $DB->get_record('user', ['username' => 'admin']);
if (!$admin) die("Admin not found.");
\core\session\manager::set_user($admin);
$USER = $admin;

echo "1. Creating Dummy Parent Course...\n";
$course = new stdClass();
$course->fullname = "Test Hierarchy Course";
$course->shortname = "THC_" . time();
$course->category = 1;
$course->summary = "Verification for new subsection engine";
$course->visible = 1;
$course = create_course($course);
echo "   Course ID: {$course->id} Created.\n\n";

echo "2. Crafting Nested Matrix Payload...\n";
$tree = [
    (object)[
        'id' => 'sec-demo',
        'name' => 'Module 1: Engine Foundation',
        'items' => [
            (object)[
                'type' => 'page',
                'name' => 'Introduction Page',
                'indent' => 0,
                'content' => 'Welcome to the core.'
            ],
            (object)[
                'type' => 'subsection',
                'name' => 'Deep Dive: Core Mechanics',
                'items' => [
                    (object)[
                        'type' => 'url',
                        'name' => 'External Architecture Reference',
                        'url' => 'https://moodle.org'
                    ],
                    (object)[
                        'type' => 'page',
                        'name' => 'Internal Matrix Documentation',
                        'content' => 'Sub-layer page active.'
                    ]
                ]
            ]
        ]
    ]
];
$json_tree = json_encode($tree);

echo "3. Triggering REST API (admin_sync_course_structure)...\n";
$_POST = [];
$_GET = ['action' => 'admin_sync_course_structure'];
$_REQUEST = ['courseid' => $course->id, 'tree' => $json_tree, 'visibility' => 1];
try {
    ob_start();
    require('public/local/api/index.php');
    ob_end_clean();
} catch (Exception $e) { /* Catch redirect exit */ }

echo "   API Payload Injected.\n\n";

echo "4. Checking Database `course_sections` for Components...\n";
$sections = $DB->get_records('course_sections', ['course' => $course->id]);
$foundSub = false;
foreach ($sections as $s) {
    if ($s->component === 'core_subsection') {
        echo "   [SUCCESS] Delegated core_subsection row found! (Section {$s->section}, ID: {$s->id})\n";
        echo "   Subsection Name: {$s->name}\n";
        echo "   Bound ItemID: {$s->itemid}\n";
        $foundSub = true;
    }
}
if (!$foundSub) {
    echo "   [FAILED] No core_subsection row was generated in database.\n";
}

echo "\n5. Validating Unflattening Mapper (admin_get_course_full)...\n";
$_GET = ['action' => 'admin_get_course_full'];
$_REQUEST = ['courseid' => $course->id];
global $_api_response;
$_api_response = [];
try {
    ob_start();
    require('public/local/api/index.php');
    ob_end_clean();
} catch (Exception $e) {}

if (!empty($_api_response['data']->tree)) {
    $parsedTree = $_api_response['data']->tree;
    // Inspect Level 1
    $firstModule = $parsedTree[0]->items[0];
    $secondModule = $parsedTree[0]->items[1];
    
    echo "   Primary Container: " . $parsedTree[0]->name . "\n";
    echo "   Item 1: " . $firstModule->name . " ({$firstModule->type})\n";
    echo "   Item 2: " . $secondModule->name . " ({$secondModule->type})\n";
    
    if ($secondModule->type === 'subsection') {
        echo "   [SUCCESS] Recursive Extraction Validated! Subsection holds " . count($secondModule->items) . " sub-nodes.\n";
        foreach ($secondModule->items as $subitem) {
             echo "      -> Nested: " . $subitem->name . " ({$subitem->type})\n";
        }
    } else {
        echo "   [FAILED] Sequence returned flat standard nodes instead of recursive map.\n";
    }
} else {
    echo "   [FAILED] No tree returned from builder.\n";
}
echo "\n--- TEST DONE ---\n";
