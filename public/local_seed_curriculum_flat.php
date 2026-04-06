<?php
/**
 * Bulk Flat Hierarchy Provisioner (First 100 Courses)
 * 
 * Reuses logic from test_course_creation.php to apply a standard
 * 3-phase curriculum to the first 100 courses in the database.
 */
if (!defined('MOODLE_INTERNAL')) {
    define('CLI_SCRIPT', true);
    require(__DIR__ . '/config.php');
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/course/modlib.php');
}

global $DB;

function bulk_update_flat_hierarchy($limit = 100) {
    global $DB;
    echo "Fetching first $limit courses...\n";
    $courses = $DB->get_records('course', [], 'id ASC', 'id, fullname, shortname', 0, $limit);
    
    if (empty($courses)) {
        echo "No courses found to update.\n";
        return;
    }

    $tree = [
        (object)[
            'name' => 'Phase 1: Curriculum Introduction',
            'items' => [
                (object)[ 'type' => 'url', 'name' => 'Phase 1: Introduction Video', 'url' => 'https://youtube.com/watch?v=dQw4w9WgXcQ' ],
                (object)[ 'type' => 'page', 'name' => 'Strategic Overview: Foundations', 'content' => '<p>Welcome to the core curriculum.</p>' ]
            ]
        ],
        (object)[
            'name' => 'Phase 2: Community & Interactive',
            'items' => [
                (object)[ 'type' => 'forum', 'name' => 'Community Collaboration Hub' ]
            ]
        ],
        (object)[
            'name' => 'Phase 3: Knowledge Validation',
            'items' => [
                (object)[ 'type' => 'quiz', 'name' => 'Curricular Mastery Assessment' ]
            ]
        ]
    ];

    foreach ($courses as $course) {
        if ($course->id == SITEID) continue;
        echo "Updating Course [{$course->id}] {$course->fullname}...\n";
        
        foreach ($tree as $index => $node) {
            $sectionnum = $index + 1;
            course_create_sections_if_missing($course->id, [(int)$sectionnum]);
            $modinfo = get_fast_modinfo($course);
            $section = $modinfo->get_section_info($sectionnum);
            
            if ($section && is_object($section)) {
                $DB->set_field('course_sections', 'name', $node->name, ['id' => $section->id]);
                
                $new_sequence = [];
                foreach ($node->items as $item) {
                    $modrec = $DB->get_record('modules', ['name' => $item->type]);
                    if (!$modrec) continue;
                    
                    $minfo = (object)[
                        'modulename' => $item->type,
                        'module' => $modrec->id,
                        'course' => $course->id,
                        'section' => $sectionnum,
                        'visible' => 1,
                        'name' => $item->name,
                        'intro' => $item->content ?? '',
                        'introformat' => FORMAT_HTML
                    ];
                    
                    if ($item->type === 'url') {
                        $minfo->externalurl = $item->url;
                        $minfo->display = 0;
                    }

                    try {
                        $new_cm = add_moduleinfo($minfo, $course);
                        if ($new_cm) $new_sequence[] = $new_cm->coursemodule;
                    } catch (Exception $e) {
                        echo "  Unit Deployment Failed for {$item->name}: " . $e->getMessage() . "\n";
                    }
                }
                $DB->set_field('course_sections', 'sequence', implode(',', $new_sequence), ['id' => $section->id]);
            }
        }
        rebuild_course_cache($course->id, true);
        echo "Hierarchy Synchronized for Course ID [{$course->id}].\n";
    }
    echo "Bulk Flat Integration Complete.\n";
}

if (PHP_SAPI === 'cli' || defined('RUN_BULK_SEED')) {
    bulk_update_flat_hierarchy(100);
}
