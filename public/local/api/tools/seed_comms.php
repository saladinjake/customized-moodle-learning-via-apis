<?php

/**
 * Universal Web Communication Seeder
 * 
 * Accessible via /local/api/tools/seed_comms.php to populate
 * the registry with sample data for high-fidelity testing.
 * 
 * Includes absolute compatibility for Moodle 4.x Event signatures.
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/message/lib.php');

global $DB, $CFG, $USER;

header('Content-Type: application/json');

try {
    $admin = get_admin();
    if (!$admin) throw new Exception("Admin identity not found.");

    $students = $DB->get_records_select('user', "deleted = 0 AND id <> ?", [$admin->id], '', 'id, username, firstname, lastname');

    if (empty($students)) {
        echo json_encode(['status' => 'error', 'message' => 'No student personas found to seed.']);
        exit;
    }

    $subjects = [
        "Registry Phase 4 Upgrade", 
        "Academic Directive Authorized", 
        "Curriculum Lock Phase 2", 
        "Final Assessment Verified"
    ];
    $bodies = [
        "Your neural profile has been upgraded to Phase 4. Transmissions authorized. <a href='#'>Verify Profile</a>", 
        "Identity verified. High-fidelity communication links are now operational and synchronized.",
        "A critical course directive has been issued for your academic profile. Action required."
    ];

    $count = 0;
    foreach ($students as $student) {
        // 1. Alert Seeding (Platform Directives)
        $eventdata = new \core\message\message();
        $eventdata->component         = 'moodle';
        $eventdata->name              = 'instantmessage';
        $eventdata->userfrom          = $admin;
        $eventdata->userto            = $student;
        $eventdata->subject           = $subjects[array_rand($subjects)];
        $eventdata->fullmessage       = $bodies[array_rand($bodies)];
        $eventdata->fullmessageformat = FORMAT_HTML;
        $eventdata->fullmessagehtml   = '<p>' . $bodies[array_rand($bodies)] . '</p>';
        $eventdata->smallmessage      = $eventdata->fullmessage;
        $eventdata->notification      = 1;
        $eventdata->courseid          = SITEID; // Critical for 4.x Event Signatures
        
        message_send($eventdata);

        // 2. Message Seeding (Direct Neural Links)
        $eventdata = new \core\message\message();
        $eventdata->component         = 'moodle';
        $eventdata->name              = 'instantmessage';
        $eventdata->userfrom          = $admin;
        $eventdata->userto            = $student;
        $eventdata->subject           = 'Direct Link';
        $eventdata->fullmessage       = "Lumina Core Sync: Identity verified. Neural transmission link active.";
        $eventdata->fullmessageformat = FORMAT_HTML;
        $eventdata->fullmessagehtml   = "<p><strong>SYNC:</strong> Identity verified. Neural transmission active.</p>";
        $eventdata->smallmessage      = $eventdata->fullmessage;
        $eventdata->notification      = 0;
        $eventdata->courseid          = SITEID; 
        
        message_send($eventdata);
        
        $count++;
    }

    echo json_encode([
        'status' => 'success',
        'message' => "Neural registry seeded for $count student personas.",
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
