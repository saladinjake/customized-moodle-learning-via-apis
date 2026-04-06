<?php
/**
 * LUMINA MASTER IDEMPOTENT SEEDER
 * 
 * Consolidates all curriculum, identity, and engagement data into one 
 * high-performance script. 
 * 
 * Features:
 * - 500 Courses with interactive modules
 * - Victor Identity Suite (Student/Instructor)
 * - Message History & Grade Ledger
 * - Cohorts & Category Registry
 * - Notifications & Calendar Events
 * - FULL IDEMPOTENCY (Safe to re-run)
 */

// define('CLI_SCRIPT', true); // Removed to allow HTTP triggering
if (!defined('NO_MOODLE_COOKIES')) {
    define('NO_MOODLE_COOKIES', true);
}
require_once(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/message/lib.php');
require_once($CFG->dirroot . '/lib/gradelib.php');
require_once($CFG->dirroot . '/enrol/manual/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/calendar/lib.php');

global $DB, $CFG, $PAGE;
// Initialize $PAGE to prevent "get_navigation_overflow_state on null" in web context
$PAGE->set_url(new moodle_url('/local_run_seed.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin'); 


function log_m($msg) { echo "[MASTER SEEDER] " . $msg . PHP_EOL; flush(); }

// --- HELPER: Provision Module ---
function provision_module($course_id, $section_num, $type, $name, $extra = []) {
    global $DB;
    $module = $DB->get_record('modules', ['name' => $type]);
    if (!$module) {
        log_m("Skipping '$name': Module plugin '$type' not installed.");
        return false;
    }
    $cw = $DB->get_record('course_sections', ['course' => $course_id, 'section' => $section_num]);
    if (!$cw) {
        $cw = new stdClass();
        $cw->course = $course_id;
        $cw->section = $section_num;
        $cw->summary = "Curricular Phase $section_num";
        $cw->summaryformat = FORMAT_HTML;
        $cw->sequence = '';
        $cw->id = $DB->insert_record('course_sections', $cw);
    }
    
    // Idempotency: check for existing instance and course_module
    $existing_inst = $DB->get_record($type, ['course' => $course_id, 'name' => $name]);
    if ($existing_inst) {
        $instance_id = $existing_inst->id;
        $existing_cm = $DB->get_record('course_modules', ['course' => $course_id, 'module' => $module->id, 'instance' => $instance_id]);
        if ($existing_cm) {
            log_m("Already exists: $type '$name' in course $course_id — skipping.");
            return $existing_cm->id;
        }
    } else {
        $mod_record = new stdClass();
        $mod_record->course = $course_id;
        $mod_record->name = $name;
        $mod_record->intro = "Machine-generated asset for Headless UX validation. Curriculum Node: $name";
        $mod_record->introformat = FORMAT_HTML;
        $mod_record->timemodified = time();

        if ($type === 'assign') { $mod_record->grade = 100; $mod_record->assignsubmission_onlinetext_enabled = 1; }
        if ($type === 'url' && isset($extra['url'])) { $mod_record->externalurl = $extra['url']; $mod_record->display = 0; }
        if ($type === 'quiz') { $mod_record->sumgrades = 10; $mod_record->grade = 10; }
        if ($type === 'page') { $mod_record->content = $extra['intro'] ?? ''; $mod_record->contentformat = FORMAT_HTML; }
        if ($type === 'scorm') { $mod_record->scormtype = 'local'; $mod_record->maxgrade = 100; }
        if ($type === 'label') { $mod_record->content = $extra['intro'] ?? ''; $mod_record->contentformat = FORMAT_HTML; }
        
        if (isset($extra['intro'])) { $mod_record->intro = $extra['intro']; $mod_record->introformat = FORMAT_HTML; }
        
        try {
            $instance_id = $DB->insert_record($type, $mod_record);
        } catch (Exception $e) {
            log_m("Could not insert '$name' ($type): " . $e->getMessage());
            return false;
        }
    }

    // Re-fetch $cw fresh to get latest sequence
    $cw = $DB->get_record('course_sections', ['course' => $course_id, 'section' => $section_num]);

    if (!$DB->record_exists('course_modules', ['course' => $course_id, 'module' => $module->id, 'instance' => $instance_id])) {
        $cm = new stdClass();
        $cm->course = $course_id;
        $cm->module = $module->id;
        $cm->instance = $instance_id;
        $cm->section = $cw->id;  // section row ID (Moodle standard)
        $cm->visible = 1;
        $cm->idnumber = "MX-$type-" . substr(md5($name), 0, 8);
        $cmid = $DB->insert_record('course_modules', $cm);
        
        // Update the sequence in course_sections so modinfo can find it
        $current_seq = $DB->get_field('course_sections', 'sequence', ['id' => $cw->id]);
        $sequence = empty($current_seq) ? (string)$cmid : $current_seq . ',' . $cmid;
        $DB->set_field('course_sections', 'sequence', $sequence, ['id' => $cw->id]);

        return $cmid;
    }

    return true;
}



// --- 1. USERS ---
log_m("Syncing Victor personas...");
$victors = [
    ['username' => 'victor_instructor', 'firstname' => 'Victor', 'lastname' => 'Instructor', 'role' => 'editingteacher'],
    ['username' => 'victor_student', 'firstname' => 'Victor', 'lastname' => 'Student', 'role' => 'student']
];

$prospects = [
    ['username' => 'student_alpha', 'firstname' => 'Alpha', 'lastname' => 'Scholar'],
    ['username' => 'student_zeta',  'firstname' => 'Zeta',  'lastname' => 'Scholar'],
    ['username' => 'student_omega', 'firstname' => 'Omega', 'lastname' => 'Scholar'],
    ['username' => 'student_theta', 'firstname' => 'Theta', 'lastname' => 'Scholar'],
];

log_m("Syncing Victor personas...");
foreach ($victors as $v) {
    if (!$DB->record_exists('user', ['username' => $v['username']])) {
        $user = new stdClass();
        $user->username          = $v['username'];
        $user->password          = hash_internal_user_password('Victor123!');
        $user->email             = "{$v['username']}@lumina.example.com";
        $user->firstname         = $v['firstname'];
        $user->lastname          = $v['lastname'];
        $user->confirmed         = 1;
        $user->lang              = 'en';
        $user->timezone          = '99';
        $user->calendartype      = 'gregorian';
        $user->mailformat        = 1;
        $user->maildisplay       = 1;
        $user->maildigest        = 0;
        $user->autosubscribe     = 1;
        $user->trackforums       = 0;
        $user->mnethostid        = $CFG->mnet_localhost_id ?? 1;
        // Mandatory Moodle 5.x schema fields
        $user->city              = '';
        $user->country           = '';
        $user->description       = '';
        $user->descriptionformat = FORMAT_HTML;
        $user->picture           = 0;
        $user->idnumber          = '';
        $user->institution       = '';
        $user->department        = '';
        $user->phone1            = '';
        $user->phone2            = '';
        $user->address           = '';
        $user->firstnamephonetic = '';
        $user->lastnamephonetic  = '';
        $user->middlename        = '';
        $user->alternatename     = '';
        $uid = user_create_user($user, false, false);
        log_m("Created user: {$v['username']} (ID: $uid)");
    } else {
        $uid = $DB->get_field('user', 'id', ['username' => $v['username']], MUST_EXIST);
    }

    // Pre-generate/Ensure Moodle Web Service token for this user
    require_once($CFG->dirroot . '/lib/externallib.php');
    $service = $DB->get_record('external_services', ['shortname' => 'moodle_mobile_app', 'enabled' => 1]);
    if (!$service) {
        $service = $DB->get_record_select('external_services', 'enabled = 1', [], '*', IGNORE_MULTIPLE);
    }
    if ($service) {
        $context = context_system::instance();
        if (!$DB->record_exists('external_tokens', ['userid' => $uid, 'externalserviceid' => $service->id])) {
            try {
                external_generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $uid, $context);
                log_m("  ↳ Generated WS Token for {$v['username']}");
            } catch (Exception $e) {
                log_m("  ⚠ Could not generate token for {$v['username']}: " . $e->getMessage());
            }
        }
    }
}

log_m("Syncing Prospect (Un-enrolled) personas...");
foreach ($prospects as $v) {
    if (!$DB->record_exists('user', ['username' => $v['username']])) {
        $user = new stdClass();
        $user->username          = $v['username'];
        $user->password          = hash_internal_user_password('Victor123!');
        $user->email             = "{$v['username']}@lumina.example.com";
        $user->firstname         = $v['firstname'];
        $user->lastname          = $v['lastname'];
        $user->confirmed         = 1;
        $user->lang              = 'en';
        $user->timezone          = '99';
        $user->calendartype      = 'gregorian';
        $user->mailformat        = 1;
        $user->maildisplay       = 1;
        $user->maildigest        = 0;
        $user->autosubscribe     = 1;
        $user->trackforums       = 0;
        $user->mnethostid        = $CFG->mnet_localhost_id ?? 1;
        // Mandatory Moodle 5.x schema fields
        $user->city              = '';
        $user->country           = '';
        $user->description       = '';
        $user->descriptionformat = FORMAT_HTML;
        $user->picture           = 0;
        $user->idnumber          = '';
        $user->institution       = '';
        $user->department        = '';
        $user->phone1            = '';
        $user->phone2            = '';
        $user->address           = '';
        $user->firstnamephonetic = '';
        $user->lastnamephonetic  = '';
        $user->middlename        = '';
        $user->alternatename     = '';
        $uid = user_create_user($user, false, false);
        log_m("Created prospect: {$v['username']} (ID: $uid)");
    } else {
        $uid = $DB->get_field('user', 'id', ['username' => $v['username']], MUST_EXIST);
    }

    // Pre-generate/Ensure Moodle Web Service token for this prospect
    require_once($CFG->dirroot . '/lib/externallib.php');
    $service = $DB->get_record('external_services', ['shortname' => 'moodle_mobile_app', 'enabled' => 1]);
    if (!$service) {
        $service = $DB->get_record_select('external_services', 'enabled = 1', [], '*', IGNORE_MULTIPLE);
    }
    if ($service) {
        $context = context_system::instance();
        if (!$DB->record_exists('external_tokens', ['userid' => $uid, 'externalserviceid' => $service->id])) {
            try {
                external_generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $uid, $context);
                log_m("  ↳ Generated WS Token for {$v['username']}");
            } catch (Exception $e) {
                log_m("  ⚠ Could not generate token for {$v['username']}: " . $e->getMessage());
            }
        }
    }
}

// --- 2. CATEGORIES ---
log_m("Syncing Categories...");
$cats = ['Software Architecture', 'Machine Learning', 'Cybersecurity', 'Financial Engineering', 'UX Research', 'Blockchain'];
foreach ($cats as $name) {
    if (!$DB->record_exists('course_categories', ['name' => $name])) {
        $cat = (object)[
            'name' => $name,
            'parent' => 0,
            'visible' => 1,
            'description' => "Category for $name curricular nodes.",
            'descriptionformat' => FORMAT_HTML
        ];
        core_course_category::create($cat);
        log_m("Created category: $name");
    } else {
        $cat = $DB->get_record('course_categories', ['name' => $name]);
        if (!$cat->visible) {
            $DB->set_field('course_categories', 'visible', 1, ['id' => $cat->id]);
            log_m("Repaired visibility for category: $name (WAS HIDDEN)");
        }
    }
}

// --- 3. COHORTS ---
log_m("Syncing Cohorts...");
$cohort_data = [
    ['name' => '2026 Spring Engineering', 'idnumber' => 'ENG-2026-SP'],
    ['name' => 'Global Leadership Network', 'idnumber' => 'GLN-MASTER']
];
foreach ($cohort_data as $c) {
    if (!$DB->record_exists('cohort', ['idnumber' => $c['idnumber']])) {
        $cohort = new stdClass();
        $cohort->name = $c['name'];
        $cohort->idnumber = $c['idnumber'];
        $cohort->contextid = context_system::instance()->id;
        cohort_add_cohort($cohort);
        log_m("Created cohort: {$c['name']}");
    }
}

// --- 4. COURSES & ENROLMENT (Loop limited to 10 for quick HTTP run, 500 in background) ---
log_m("Syncing Course Matrix (High-Density)...");
$topics = ['Cloud Architecture', 'Neural Networks', 'Quantum Logic', 'Agile Scale', 'Bio-Informatics'];
$all_cats = $DB->get_records('course_categories', [], 'id ASC');
$cat_ids = array_keys($all_cats);

$existing_courses = array_values($DB->get_records_select('course', 'id != 1', null, 'id ASC', '*', 0, 150));
for ($i = 0; $i < 150; $i++) { 
    if (isset($existing_courses[$i])) {
        $course_obj = $existing_courses[$i];
        if (!$course_obj->visible) {
            $DB->set_field('course', 'visible', 1, ['id' => $course_obj->id]);
            log_m("Repaired visibility for course: " . $course_obj->shortname);
            rebuild_course_cache($course_obj->id, true);
        }
    } else {
        $topic = $topics[array_rand($topics)];
        $cat_id = empty($cat_ids) ? 1 : $cat_ids[array_rand($cat_ids)];
        $shortname = "MX-150-" . ($i+1) . "-" . time();
        $course = new stdClass();
        $course->fullname = "[" . ($i+1) . "] " . $topic . " Mastery";
        $course->shortname = $shortname;
        $course->category = $cat_id;
        $course->format = 'topics';
        $course->numsections = 4;
        $course->visible = 1;
        $course->summary = "Strategic curriculum node for $shortname.";
        $course->summaryformat = FORMAT_HTML;
        $course->timecreated = time();
        $course->timemodified = time();
        $course_obj = create_course($course);
        log_m("Created course: $shortname");
    }

    // High-Fidelity Asset Provisioning (Multi-Section)
    course_create_sections_if_missing($course_obj->id, [1, 2, 3, 4]);

    $tree = [
        (object)[
            'name' => 'Phase 1: Foundation',
            'items' => [
                (object)[
                    'type' => 'url',
                    'name' => 'Phase 1: Video Introduction',
                    'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
                ]
            ]
        ],
        (object)[
            'name' => 'Phase 2: Strategy Simulator',
            'items' => [
                (object)[ 'type' => 'forum', 'name' => 'Phase 2: Community Collaboration Hub' ],
                (object)[
                    'type' => 'subsection',
                    'name' => 'Deep Dive: Strategy',
                    'items' => [
                        (object)[ 'type' => 'scorm', 'name' => 'Interactive Strategy Simulator' ]
                    ]
                ]
            ]
        ],
        (object)[
            'name' => 'Phase 3: Assessments',
            'items' => [
                (object)[ 'type' => 'quiz', 'name' => 'Phase 3: Curricular Mastery Assessment' ]
            ]
        ],
        (object)[
            'name' => 'Phase 4: Capstone',
            'items' => [
                 (object)[ 'type' => 'assign', 'name' => 'Phase 4: Capstone Milestone' ]
            ]
        ]
    ];

    foreach ($tree as $index => $node) {
        $sectionnum = $index + 1;
        
        // Direct DB lookup for the section to avoid stale modinfo cache
        $section = $DB->get_record('course_sections', ['course' => $course_obj->id, 'section' => $sectionnum]);
        if (!$section) {
            // Create if missing (though course_create_sections_if_missing should have handled it)
            $section = new stdClass();
            $section->course = $course_obj->id;
            $section->section = $sectionnum;
            $section->summary = "";
            $section->summaryformat = FORMAT_HTML;
            $section->id = $DB->insert_record('course_sections', $section);
        }

        if ($section) {
            $DB->set_field('course_sections', 'name', $node->name, ['id' => $section->id]);
            foreach ($node->items as $item) {
                if (($item->type ?? '') === 'subsection') {
                     // Check if this subsection already exists (anchored to a label with this name)
                     $existing_anchor = $DB->get_record_sql("
                        SELECT cm.id 
                        FROM {course_modules} cm
                        JOIN {modules} m ON m.id = cm.module
                        JOIN {label} l ON l.id = cm.instance
                        WHERE cm.course = ? AND m.name = 'label' AND l.name = ? AND l.intro = '<!-- subsection -->'", 
                        [$course_obj->id, $item->name]
                     );

                     if ($existing_anchor) {
                         $anchor_cmid = $existing_anchor->id;
                         $sub_sec = $DB->get_record('course_sections', ['course' => $course_obj->id, 'component' => 'core_subsection', 'itemid' => $anchor_cmid]);
                         $sub_sec_num = $sub_sec ? $sub_sec->section : null;
                     } else {
                         $maxsec = (int)$DB->get_field_sql("SELECT MAX(section) FROM {course_sections} WHERE course = ?", [$course_obj->id]);
                         $sub_sec_num = $maxsec + 1;
                         $anchor_cmid = provision_module($course_obj->id, $sectionnum, 'label', $item->name, ['intro' => '<!-- subsection -->']);
                     }
                     
                     if ($anchor_cmid) {
                         if (!$DB->record_exists('course_sections', ['course' => $course_obj->id, 'component' => 'core_subsection', 'itemid' => $anchor_cmid])) {
                             $delegated = new \stdClass();
                             $delegated->course = $course_obj->id;
                             $delegated->section = $sub_sec_num;
                             $delegated->name = $item->name;
                             $delegated->summary = '';
                             $delegated->summaryformat = FORMAT_HTML;
                             $delegated->sequence = '';
                             $delegated->visible = 1;
                             $delegated->component = 'core_subsection';
                             $delegated->itemid = $anchor_cmid;
                             $dsid = $DB->insert_record('course_sections', $delegated);
                         } else {
                             $sub_sec = $DB->get_record('course_sections', ['course' => $course_obj->id, 'component' => 'core_subsection', 'itemid' => $anchor_cmid]);
                             $sub_sec_num = $sub_sec->section;
                         }
                         
                         if (!empty($item->items)) {
                             foreach ($item->items as $subitem) {
                                 $extra = [];
                                 if (isset($subitem->url)) $extra['url'] = $subitem->url;
                                 if (isset($subitem->content)) $extra['intro'] = $subitem->content;
                                 provision_module($course_obj->id, $sub_sec_num, $subitem->type, $subitem->name, $extra);
                             }
                         }
                     }
                } else {
                     $extra = [];
                     if (isset($item->url)) $extra['url'] = $item->url;
                     if (isset($item->content)) $extra['intro'] = $item->content;
                     provision_module($course_obj->id, $sectionnum, $item->type, $item->name, $extra);
                }
            }
        }
    }

    // Rebuild cache for performance in frontend
    rebuild_course_cache($course_obj->id, true);

    // Auto-Enrol Victors
    $enrol = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $course_obj->id, 'enrol' => 'manual'], '*', MUST_EXIST);
    foreach ($victors as $v) {
        $user_obj = $DB->get_record('user', ['username' => $v['username']], '*', MUST_EXIST);
        $role_obj = $DB->get_record('role', ['shortname' => $v['role']], '*', MUST_EXIST);
        if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user_obj->id])) {
            $enrol->enrol_user($instance, $user_obj->id, $role_obj->id);
        }
    }
}

// --- 5. MESSAGES, GRADES, & NOTIFICATIONS ---
log_m("Injecting Engagement Data...");
$student = $DB->get_record('user', ['username' => 'victor_student'], '*', MUST_EXIST);
$teacher = $DB->get_record('user', ['username' => 'victor_instructor'], '*', MUST_EXIST);

// Messages
$msg1 = "Welcome to the Digital Frontier. Your access is now provisioned.";
if (!$DB->record_exists('messages', ['smallmessage' => $msg1])) {
    $c = \core_message\api::create_conversation(\core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL, [$teacher->id, $student->id]);
    \core_message\api::send_message_to_conversation($teacher->id, $c->id, $msg1, FORMAT_HTML);
    log_m("Sent introductory message.");
}

// Calendar Event
if (!$DB->record_exists('event', ['name' => 'Phase 4 Submission Deadline'])) {
    $event = new stdClass();
    $event->name = 'Phase 4 Submission Deadline';
    $event->description = 'Final assessment for the curriculum track.';
    $event->format = FORMAT_HTML;
    $event->courseid = 0; // Site event
    $event->userid = $student->id;
    $event->modulename = 0;
    $event->instance = 0;
    $event->eventtype = 'user';
    $event->timestart = time() + (86400 * 7); // 1 week from now
    $event->timeduration = 0;
    calendar_event::create($event, false);
    log_m("Created calendar event.");
}

log_m("=== MASTER SEEDING COMPLETE ===");
