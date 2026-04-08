<?php
define('CLI_SCRIPT', true);
define('NO_API_EXIT', true);
require('config.php');
global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

global $DB, $USER;
$admin = $DB->get_record('user', ['username' => 'admin']);
if (!$admin) die("Admin not found.");
\core\session\manager::set_user($admin);
$USER = $admin;

$token = $DB->get_record('external_tokens', ['userid' => $admin->id, 'tokentype' => EXTERNAL_TOKEN_PERMANENT]);
if (!$token) die("Admin token not found. Please create one for 'moodle_mobile_app'.");
$wstoken = $token->token;

$_SERVER['REQUEST_METHOD'] = 'POST';

for ($i = 1; $i <= 10; $i++) {
    echo "Creating Nested Course $i...\n";
    $course = new stdClass();
    $course->fullname = "Nested Test Course $i";
    $course->shortname = "NEST_" . $i . "_" . time();
    $course->category = 1;
    $course->summary = "Automated nested structure verification - Course $i";
    $course->visible = 1;
    $course = create_course($course);
    echo "   Course ID: {$course->id} Created.\n";

    $tree = [
        (object)[
            'id' => 'sec-' . $i,
            'name' => "Section 1: The Foundation (Course $i)",
            'items' => [
                (object)[
                    'type' => 'page',
                    'name' => 'Intro Chapter',
                    'content' => 'Content for level 1.'
                ],
                (object)[
                    'type' => 'subsection',
                    'name' => 'Subsection Level 2',
                    'items' => [
                        (object)[
                            'type' => 'url',
                            'name' => 'Support Link',
                            'url' => 'https://moodle.org'
                        ],
                        (object)[
                            'type' => 'subsection',
                            'name' => 'Deep Subsection Level 3',
                            'items' => [
                                (object)[
                                    'type' => 'page',
                                    'name' => 'Level 3 Note',
                                    'content' => 'Deeply nested content.'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    $json_tree = json_encode($tree);
    
    $_POST = ['courseid' => $course->id, 'tree' => $json_tree, 'visibility' => 1, 'wstoken' => $wstoken];
    $_GET = ['action' => 'admin_sync_course_structure', 'wstoken' => $wstoken];
    $_REQUEST = array_merge($_GET, $_POST);
    
    try {
        ob_start();
        $res = require('public/local/api/index.php');
        ob_end_clean();
        echo "   [SUCCESS] Structure synced for Course $i\n";
    } catch (Exception $e) {
        echo "   [ERROR] Failed to sync structure for Course $i: " . $e->getMessage() . "\n";
    }
    echo "-------------------------------------------\n";
}

echo "\n--- ALL 10 COURSES CREATED ---\n";
