<?php
define('CLI_SCRIPT', true);
require('public/config.php');
require_once($CFG->dirroot . '/course/lib.php');

$c_data = json_decode('{"fullname":"Test Course XYZ","shortname":"TCXYZ_123","category":"1"}');
$c_obj = (object)[
    'fullname' => $c_data->fullname, 
    'shortname' => $c_data->shortname, 
    'category' => $c_data->category, 
    'summary' => $c_data->summary ?? '', 
    'format' => 'topics', 
    'numsections' => count($c_data->sections ?? []), 
    'visible' => 0
];
try {
    $n_course = create_course($c_obj);
    echo "Created: " . $n_course->id . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
