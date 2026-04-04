<?php

/**
 * Enterprise Headless Moodle Matrix Seeder (Enhanced High-Density v2)
 * 
 * Provisions 500 courses with 2000+ entities, including YouTube video resources
 * for every curriculum node to validate high-fidelity media playback.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/public/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/grouplib.php');
require_once($CFG->dirroot . '/enrol/manual/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

global $DB, $CFG;

// Prevent email errors during bulk actions
$CFG->noreplyaddress = 'noreply@example.com';

function log_seed($msg) {
    echo "[MATRIX SEEDER] " . $msg . PHP_EOL;
}

// Improved Module Provisioner with Interactive & Metadata Support
function provision_module($course_id, $section_num, $type, $name, $extra = []) {
    global $DB;
    $module = $DB->get_record('modules', ['name' => $type], '*', MUST_EXIST);
    $cw = $DB->get_record('course_sections', ['course' => $course_id, 'section' => $section_num], '*', MUST_EXIST);
    
    $mod_record = new stdClass();
    $mod_record->course = $course_id;
    $mod_record->name = $name;
    $mod_record->intro = "Machine-generated asset for Headless UX validation. Curriculum Node: $name";
    $mod_record->introformat = FORMAT_HTML;
    $mod_record->timemodified = time();

    if ($type === 'assign') {
        $mod_record->grade = 100;
        $mod_record->assignsubmission_onlinetext_enabled = 1;
    }
    if ($type === 'url' && isset($extra['url'])) {
        $mod_record->externalurl = $extra['url'];
        $mod_record->display = 0; // Default
    }
    if ($type === 'quiz') {
        $mod_record->intro = "Interactive assessment for node $name.";
        $mod_record->timeopen = time();
        $mod_record->timeclose = 0;
        $mod_record->preferredbehaviour = 'deferredfeedback';
        $mod_record->attempts = 0; // Unlimited
        $mod_record->grademethod = 1; // Best grade
        $mod_record->decimalpoints = 2;
        $mod_record->questiondecimalpoints = -1;
        $mod_record->sumgrades = 10;
        $mod_record->grade = 10;
    }
    if ($type === 'forum') {
        $mod_record->type = 'general';
        $mod_record->forcesubscribe = 0;
    }
    if ($type === 'scorm') {
        $mod_record->version = 'SCORM_1.2';
        $mod_record->maxgrade = 100;
        $mod_record->reference = "INTERNAL_GAME_V1";
    }
    
    // Inject custom HTML intro if provided for POC validation
    if (isset($extra['intro'])) {
        $mod_record->intro = $extra['intro'];
        $mod_record->introformat = FORMAT_HTML;
    }
    
    $instance_id = $DB->insert_record($type, $mod_record);
    
    $cm = new stdClass();
    $cm->course = $course_id;
    $cm->module = $module->id;
    $cm->instance = $instance_id;
    $cm->section = $cw->id;
    $cm->visible = 1;
    $cm->completion = 1; // 🛡️ Enable Progress Tracking (View to Complete)
    $cm->completionview = 1;
    $cm->idnumber = "MX-$type-" . uniqid();
    $cmid = $DB->insert_record('course_modules', $cm);
    
    // Link to section sequence
    $sequence = empty($cw->sequence) ? $cmid : $cw->sequence . ',' . $cmid;
    $DB->set_field('course_sections', 'sequence', $sequence, ['id' => $cw->id]);
    
    // Specialized setup for complex modules
    if ($type === 'quiz') setup_quiz_questions($instance_id);
    if ($type === 'forum') setup_forum_discussion($instance_id, $course_id);

    rebuild_course_cache($course_id, true);
    log_seed("Provisioned $type: $name in $course_id S$section_num");
    return $cmid;
}

// Mock Question Seeder for Quizzes (Headless Metadata Injection)
function setup_quiz_questions($quiz_id) {
    global $DB;
    $questions = [
        ['id' => 1, 'type' => 'multichoice', 'text' => 'Which protocol ensures high-fidelity headless streaming?', 'options' => ['Websocket', 'REST/JSON', 'SOAP', 'gRPC'], 'answer' => 1],
        ['id' => 2, 'type' => 'dragdrop', 'text' => 'Drag the correct verification token to its registry node.', 'zones' => ['Auth', 'System', 'Audit'], 'items' => ['Token_A', 'Token_B', 'Token_C']],
        ['id' => 3, 'type' => 'truefalse', 'text' => 'Is low-latency necessary for agentic UX?', 'answer' => true]
    ];
    
    $intro = "Interactive assessment specialized for this curriculum node.\n<!-- HEADLESS_QUESTIONS: " . json_encode($questions) . " -->";
    $DB->set_field('quiz', 'intro', $intro, ['id' => $quiz_id]);
}

// Mock Forum Thread Seeder
function setup_forum_discussion($forum_id, $course_id) {
    global $DB;
    $disc = new stdClass();
    $disc->course = $course_id;
    $disc->forum = $forum_id;
    $disc->name = "Welcome to the Digital Frontier";
    $disc->firstpost = 0; // Will be set below
    $disc->userid = 2; // Admin
    $disc->timemodified = time();
    $disc_id = $DB->insert_record('forum_discussions', $disc);
    
    $post = new stdClass();
    $post->discussion = $disc_id;
    $post->parent = 0;
    $post->userid = 2;
    $post->subject = $disc->name;
    $post->message = "Please share your learning objectives for this track below. Our community is here to support your growth.";
    $post->messageformat = FORMAT_HTML;
    $post->created = time();
    $post->modified = time();
    $post_id = $DB->insert_record('forum_posts', $post);
    
    $DB->set_field('forum_discussions', 'firstpost', $post_id, ['id' => $disc_id]);
}

// -------------------------------------------------------------------------
// 1. DATA VECTORS: YouTube & Curriculum Topics
// -------------------------------------------------------------------------
$vids = [
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ', // Rickroll Reference
    'https://www.youtube.com/watch?v=kYfNC5_S5vY', // Moodle Demo
    'https://www.youtube.com/watch?v=9P6rdqiybew', // Physics
    'https://www.youtube.com/watch?v=F3z7SSTeEUM', // Design
    'https://www.youtube.com/watch?v=mD07A1J4t4o', // Strategy
    'https://www.youtube.com/watch?v=w7ejm6E_pB4', // Cyber
    'https://www.youtube.com/watch?v=5qap5aO4i9A', // Data
    'https://www.youtube.com/watch?v=vLnPwxZdW4Y', // AI
];

$docs = [
    '/material-w1.txt',
    '/certification-guide.pdf'
];

$topics = [
    'Blockchain Infrastructure', 'Quantum Cryptography', 'Edge Computing', 
    'Zero Trust Security', 'Autonomous Systems', 'Bio-Informatics',
    'Financial Engineering', 'Sustainable Energy Systems', 'Agile Orchestration',
    'Cognitive Psychometrics', 'Neuromorphic Hardware', 'Differentiable Programming',
    'Web3 Development', 'Full-Stack Engineering', 'UX Research Mastery',
    'Cloud Native Architecture', 'Advanced Robotics', 'Machine Learning Ops'
];

// -------------------------------------------------------------------------
// 2. IDENTITY CHECK
// -------------------------------------------------------------------------
$victors = ['victor_instructor', 'victor_student'];
foreach($victors as $v_username) {
    if (!$DB->record_exists('user', ['username' => $v_username])) {
        // Fallback: create mock users if they disappeared
        $user = new stdClass();
        $user->username = $v_username;
        $user->email = "$v_username@example.com";
        $user->firstname = 'Victor';
        $user->lastname = ($v_username == 'victor_instructor' ? 'Instructor' : 'Student');
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        user_create_user($user);
    }
}

// -------------------------------------------------------------------------
// 3. MASS CURRICULUM DEPLOYMENT (500 COURSES)
// -------------------------------------------------------------------------
// Fetch seeded categories
$cats = $DB->get_records('course_categories', ['parent' => 0], 'id ASC');
$cat_ids = array_keys($cats);

if (empty($cat_ids)) {
    $cat_ids = [1]; // Fallback to Misc if no categories found
}

for ($i = 1; $i <= 500; $i++) {
    $topic = $topics[array_rand($topics)];
    $cat_id = $cat_ids[array_rand($cat_ids)];
    $cat_name = $cats[$cat_id]->name ?? 'General';

    $fullname = "[$i] $topic Specialization ($cat_name)";
    $shortname = "MX-500-$i";
    
    $existing = $DB->get_record('course', ['shortname' => $shortname]);
    if ($existing) {
        log_seed("Updating existing node: $shortname");
        $course_id = $existing->id;
    } else {
        $course_data = new stdClass();
        $course_data->fullname = $fullname;
        $course_data->shortname = $shortname;
        $course_data->category = $cat_id;
        $course_data->format = 'topics';
        $course_data->numsections = 4;
        $course_data->summary = "An intensive curriculum covering $topic. Anchored in the $cat_name specialty registry.";
        
        $new_course = create_course($course_data);
        $course_id = $new_course->id;
        log_seed("Course anchored: $shortname in Cat:$cat_id");
    }

    // Mass Enrolment Victor Persona
    $enrol_p = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $course_id, 'enrol' => 'manual'], '*', MUST_EXIST);
    
    foreach(['editingteacher' => 'victor_instructor', 'student' => 'victor_student'] as $role => $uname) {
        $r_obj = $DB->get_record('role', ['shortname' => $role], '*', MUST_EXIST);
        $u_obj = $DB->get_record('user', ['username' => $uname], '*', MUST_EXIST);
        $enrol_p->enrol_user($instance, $u_obj->id, $r_obj->id);
    }

    // High-Fidelity Asset Provisioning
    course_create_sections_if_missing($course_id, [1, 2, 3, 4]);
    
    // SECTION 1: VIDEO PREVIEW & HTML POC
    $vid_url = $vids[array_rand($vids)];
    provision_module($course_id, 1, 'url', "Phase 1: Curriculum Introduction (Video)", ['url' => $vid_url]);
    
    // HTML POC: Large Academic Syllabus Overview
    $html_content = "
        <div class='academy-poc'>
            <h3>Academic Deep-Dive: Enterprise Orchestration v2.4</h3>
            <p>This comprehensive module provides a high-fidelity overview of the modern curricular framework. It is intended to validate the <b>Academy Page</b> rendering capabilities of the Work Studio environment.</p>
            <ul>
                <li><b>Strategic Alignment</b>: Ensuring that all educational vectors are aligned with the primary industry standards.</li>
                <li><b>Modular Architecture</b>: Discussing the decoupling of monolithic curriculum into agentic learning nodes.</li>
                <li><b>Interactive Validation</b>: Utilizing high-density HTML previews to ensure perfect student comprehension.</li>
            </ul>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
            <h4>Module Track Overview</h4>
            <p>During this session, we will explore the intersection of <i>Agentic UX</i> and <i>Headless Moodle Architectures</i>. This involves a rigorous analysis of API stability and media rendering across non-standard local development ports.</p>
            <blockquote>'The future of digital learning is not just in the content, but in the immersive precision of the player surface.' - Lumina Academy Design Principles</blockquote>
        </div>
    ";
    provision_module($course_id, 1, 'page', "Proof of Concept: Strategic Overview", ['intro' => $html_content]);
    
    // SECTION 2: COMMUNITY & INTERACTIVE
    provision_module($course_id, 2, 'forum', "Phase 2: Community Collaboration Hub");
    provision_module($course_id, 2, 'scorm', "Phase 2: Interactive Strategy Simulator (Game)");
    
    // SECTION 3: KNOWLEDGE VALIDATION
    provision_module($course_id, 3, 'quiz', "Phase 3: Curricular Mastery Assessment");
    
    // SECTION 4: CAPSTONE
    provision_module($course_id, 4, 'assign', "Phase 4: Capstone Performance Milestone");
}

log_seed("MATRIX SEEDING COMPLETE: 500 CLUSTERS FULLY ANCHORED WITH BINARY PREVIEWS.");
