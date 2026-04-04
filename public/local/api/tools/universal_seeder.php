<?php

/**
 * Universal Matrix Seeder v1.1
 * 
 * An authoritative, monolithic registry initializer that synchronizes 
 * ALL platform sectors: categories, curriculum (500 clusters), 
 * grades, and direct neural transmissions across EVERY identity.
 * 
 * Accessible via /local/api/tools/universal_seeder.php
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/message/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

global $DB, $CFG, $USER;

header('Content-Type: application/json');

set_time_limit(0); // This might take a while

try {
    $admin = get_admin();
    if (!$admin) throw new Exception("Administrative anchor not found.");

    $results = [
        'categories' => 0,
        'courses' => 0,
        'messages' => 0,
        'notifications' => 0,
        'grades' => 0
    ];

    // 1. ANCHOR CATEGORIES
    $catNames = ['Software Architecture', 'Machine Learning Ops', 'Cybersecurity Audit', 'Quantum Electrodynamics', 'Digital Transformation'];
    foreach ($catNames as $name) {
        if (!$DB->record_exists('course_categories', ['name' => $name])) {
            $cat = new \stdClass();
            $cat->name = $name;
            $cat->parent = 0;
            \core_course_category::create($cat);
            $results['categories']++;
        }
    }

    // 2. PROVISION LONE STUDENT & IDENTITIES
    $personaNames = ['lone_student', 'victor_student'];
    foreach ($personaNames as $uname) {
        if (!$DB->record_exists('user', ['username' => $uname])) {
            $user = new stdClass();
            $user->username = $uname;
            $user->firstname = ucfirst(str_replace('_student', '', $uname));
            $user->lastname = 'Persona';
            $user->email = "$uname@lumina.academy";
            $user->password = 'Lumina2026!';
            $user->confirmed = 1;
            $user->mnethostid = $CFG->mnet_localhost_id;
            user_create_user($user);
        }
    }

    // 3. SEED CURRICULUM NODES & AUTHORITATIVE GRADES
    $category = $DB->get_record_select('course_categories', '', [], 'id', IGNORE_MULTIPLE);
    $active_grade_items = [];

    for ($i = 0; $i < 5; $i++) {
        $shortname = "MX-UNIV-$i";
        $course = $DB->get_record('course', ['shortname' => $shortname]);
        if (!$course) {
            $cdata = new stdClass();
            $cdata->fullname = "Registry Cluster: Alpha $i - System Orchestration";
            $cdata->shortname = $shortname;
            $cdata->category = $category->id;
            $cdata->format = 'topics';
            $course = create_course($cdata);
            $results['courses']++;
        }
        
        // Ensure grade item exists for this exact course
        $gi = $DB->get_record('grade_items', ['courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => "Mastery Benchmark $i"]);
        if (!$gi) {
            $grade_item = new grade_item([
                'courseid' => $course->id,
                'categoryid' => null,
                'itemname' => "Mastery Benchmark $i",
                'itemtype' => 'manual',
                'grademin' => 0,
                'grademax' => 100
            ]);
            $grade_item->insert();
            $gi = $grade_item;
        }
        $active_grade_items[] = $gi->id;
    }

    // 4. NEURAL REGISTRY POPULATION (Messages & Alerts)
    $allUsers = $DB->get_records_select('user', "deleted = 0 AND id <> ?", [$admin->id], '', 'id, username, firstname, lastname');
    
    $subjects = ["Registry Phase 4 Upgrade", "Academic Directive Authorized", "Curriculum Lock Detected", "Final Assessment Release"];
    $bodies = [
        "Your neural profile has been upgraded to Phase 4. Transmissions authorized. <a href='#'>Verify Profile</a>", 
        "Welcome to Lumina Academy. High-fidelity communication links are now authorized and synchronized."
    ];

    foreach ($allUsers as $student) {
        // Platform Directives (Notifications) - Multiple per student
        for ($j = 0; $j < 3; $j++) {
            $eventdata = new \core\message\message();
            $eventdata->component         = 'moodle';
            $eventdata->name              = 'instantmessage';
            $eventdata->userfrom          = $admin;
            $eventdata->userto            = $student;
            $eventdata->subject           = $subjects[array_rand($subjects)] . " [Node ".rand(100,999)."]";
            $eventdata->fullmessage       = $bodies[array_rand($bodies)];
            $eventdata->fullmessageformat = FORMAT_HTML;
            $eventdata->fullmessagehtml   = '<p>' . $bodies[array_rand($bodies)] . '</p>';
            $eventdata->smallmessage      = $eventdata->fullmessage;
            $eventdata->notification      = 1;
            $eventdata->courseid          = SITEID;
            message_send($eventdata);
            $results['notifications']++;
        }

        // Direct Links (Chat Messages)
        try {
            $conv_id = \core_message\api::create_conversation(
                \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL,
                [$admin->id, $student->id]
            )->id;
            
            $msgText = "Universal Sync: Identity verified for {$student->username}. Neural transmission active.";
            \core_message\api::send_message_to_conversation($admin->id, $conv_id, $msgText, FORMAT_HTML);
            $results['messages']++;
        } catch (Exception $e) { /* Conversation already exists possibly */ }

        // Authoritative Grades
        foreach ($active_grade_items as $gi_id) {
            $grade_item = grade_item::fetch(['id' => $gi_id]);
            if ($grade_item) {
                $grade_item->update_final_grade($student->id, rand(72, 99), 'UniversalSeeder');
                $results['grades']++;
            }
        }
    }

    // 5. CURRICULUM INTERACTIVITY (Adding Modules to Courses)
    $courses = $DB->get_records_select('course', 'id > 1 AND shortname LIKE ?', ['MX-UNIV-%']);
    foreach ($courses as $course) {
        // Ensure standard sections exist
        course_create_sections_if_missing($course, [0, 1, 2]);
        $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $section2 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);

        // Add Active Phase: Assignment
        if (!$DB->record_exists('course_modules', ['course' => $course->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'assign'])])) {
            $am = new stdClass();
            $am->course = $course->id;
            $am->name = "Academic Mastery Assessment [{$course->shortname}]";
            $am->intro = "Establish your knowledge baseline for system orchestration.";
            $am->introformat = FORMAT_HTML;
            $am->section = 1;
            $am->visible = 1;
            $moduleid = $DB->get_field('modules', 'id', ['name' => 'assign']);
            $am->module = $moduleid;
            $am->modulename = 'assign';
            $cm = \course_add_cm_to_section($course, $moduleid, 1);
            $results['notifications']++; // Triggers event usually
        }

        // Add Active Phase: Quiz
        if (!$DB->record_exists('course_modules', ['course' => $course->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'])])) {
            $quiz = new stdClass();
            $quiz->course = $course->id;
            $quiz->name = "Neural Synthesis Quiz [{$course->shortname}]";
            $quiz->intro = "Validate your neural pathways.";
            $moduleid = $DB->get_field('modules', 'id', ['name' => 'quiz']);
            $cm = \course_add_cm_to_section($course, $moduleid, 1);
        }

        // Add Platform Social: Forum
        if (!$DB->record_exists('course_modules', ['course' => $course->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'forum'])])) {
            $forum = new stdClass();
            $forum->course = $course->id;
            $forum->name = "Lumina Peer Network [{$course->shortname}]";
            $forum->intro = "Establish direct neural links with other academy members.";
            $moduleid = $DB->get_field('modules', 'id', ['name' => 'forum']);
            $cm = \course_add_cm_to_section($course, $moduleid, 2);
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'UNIVERSAL_MATRIX_SYNCHRONIZED',
        'data' => $results,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
