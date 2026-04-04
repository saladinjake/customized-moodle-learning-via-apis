<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/guest/lib.php');

global $DB;

$courses = $DB->get_records('course', [], '', 'id, shortname');

foreach ($courses as $c) {
    if ($c->id == 1) continue;
    
    $enrol = $DB->get_record('enrol', ['courseid' => $c->id, 'enrol' => 'guest']);
    
    if ($enrol) {
        if ($enrol->status != ENROL_INSTANCE_ENABLED) {
            $DB->update_record('enrol', ['id' => $enrol->id, 'status' => ENROL_INSTANCE_ENABLED]);
            echo "Enabled Guest Enrol for Course {$c->id}\n";
        }
    } else {
        // Add guest enrol instance
        $plugin = enrol_get_plugin('guest');
        $plugin->add_instance($c);
        echo "Added Guest Enrol Instance for Course {$c->id}\n";
    }
    
    // Also ensure guest access is allowed in course settings
    if ($DB->record_exists('course', ['id' => $c->id])) {
        // Moodle course table doesn't have a direct 'guest' field usually, it's via enrol_guest.
    }
}

// Clear cache
purge_all_caches();
echo "Guest Access Provisioning Complete.\n";
