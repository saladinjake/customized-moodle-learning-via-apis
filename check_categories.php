<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
global $DB;

try {
    $cats = $DB->get_records('course_categories', null, '', 'id, name, parent');
    echo "SUMMARY: " . count($cats) . " Categories Found.\n";
    foreach ($cats as $cat) {
        echo " - [ID:{$cat->id}] Parent:{$cat->parent} Name:{$cat->name}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
