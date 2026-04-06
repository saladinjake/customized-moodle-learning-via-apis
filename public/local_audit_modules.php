<?php
require_once(__DIR__ . '/config.php');
global $DB;
header('Content-Type: text/plain');

$modules = $DB->get_records('modules');
echo "INSTALLED MODULES:\n";
foreach ($modules as $m) {
    echo " - {$m->name}\n";
}
