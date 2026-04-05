<?php
/**
 * CATALOG AUDIT (INTERNAL)
 */
// define('CLI_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

global $DB;

header('Content-Type: text/plain');
echo "=== CATALOG CATEGORY AUDIT ===\n\n";

$categories = $DB->get_records_sql("
    SELECT c.id, c.name, (SELECT COUNT(*) FROM {course} WHERE category = c.id) as course_count
    FROM {course_categories} c
    ORDER BY c.id ASC
");

printf("%-5s | %-30s | %-12s\n", "ID", "CATEGORY NAME", "COURSE COUNT");
echo str_repeat("-", 55) . "\n";

foreach ($categories as $c) {
    printf("%-5d | %-30s | %-12d\n", $c->id, $c->name, $c->course_count);
}

echo "\n=== END AUDIT ===\n";
