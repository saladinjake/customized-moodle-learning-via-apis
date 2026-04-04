<?php

/**
 * Universal Communication & Neural Registry Seeder
 * 
 * Broadly populates the Moodle database with sample messages 
 * and notifications across ALL active student personas to enable 
 * high-fidelity testing of the Communication Hubs.
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/message/lib.php');

global $DB, $CFG;

list($options, $unrecognized) = cli_get_params(
    ['help' => false, 'messages' => 10, 'notifications' => 5],
    ['h' => 'help']
);

if ($options['help']) {
    echo "Lumina Academy Seeder: populates messaging and notification tables.\n";
    echo "Usage: php seed_messages.php --messages=20 --notifications=10\n";
    exit(0);
}

// 1. Identify Admin & Personas
$admin = get_admin();
if (!$admin) {
    cli_error("CRITICAL: Admin user not found. Aborting neural seed.");
}

$students = $DB->get_records_select('user', "deleted = 0 AND id <> ?", [$admin->id], '', 'id, username, firstname, lastname');

if (empty($students)) {
    cli_problem("No student personas detected. Seeding 0 links.");
    exit(1);
}

echo "Initiating Neural Seed for " . count($students) . " personas...\n";

$subjects = [
    "Platform Update: Neural Registry Stage 4",
    "Academic Directive: Final Assessment Parameters",
    "Lumina Core: System Resilience Notification",
    "Neural Link Established",
    "Operational Update: Curriculum Lock Phase 2"
];

$bodies = [
    "Your neural profile has been upgraded to Stage 4. Please verify your direct links.",
    "The final assessment parameters have been verified. You are authorized to proceed.",
    "System stability at 99.8%. No anomalies detected in your academic ledger.",
    "Welcome to the Lumina Academy platform. Your communication hubs are now operational.",
    "A new course directive has been issued for your curriculum path. Check your active courses."
];

foreach ($students as $student) {
    echo "  - Syncing Persona: {$student->username}\n";

    // A. Seed Notifications (Platform Directives)
    for ($i = 0; $i < $options['notifications']; $i++) {
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
        $eventdata->notification      = 1; // It's an alert
        $eventdata->contexturl        = $CFG->wwwroot;
        $eventdata->contexturlname    = 'Lumina Dashboard';
        
        message_send($eventdata);
    }

    // B. Seed Messages (Direct Neural Links)
    // Between Admin and Student
    for ($i = 0; $i < $options['messages']; $i++) {
        $eventdata = new \core\message\message();
        $eventdata->component         = 'moodle';
        $eventdata->name              = 'instantmessage';
        $eventdata->userfrom          = ($i % 2 == 0) ? $admin : $student;
        $eventdata->userto            = ($i % 2 == 0) ? $student : $admin;
        $eventdata->subject           = 'Direct Link';
        $eventdata->fullmessage       = "Transmission payload index: #$i. Authoritative link valid.";
        $eventdata->fullmessageformat = FORMAT_HTML;
        $eventdata->fullmessagehtml   = "<p>Transmission payload index: #$i. <strong>Authoritative link valid.</strong></p>";
        $eventdata->smallmessage      = $eventdata->fullmessage;
        $eventdata->notification      = 0; // It's a real chat message
        
        message_send($eventdata);
    }
}

echo "\nSEED SUCCESSFUL: Neural registry populated for " . count($students) . " identities.\n";
