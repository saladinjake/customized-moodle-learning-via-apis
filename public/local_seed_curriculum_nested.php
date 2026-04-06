<?php
/**
 * Bulk Nested Hierarchy Provisioner (Remaining Courses)
 * 
 * Reuses logic from test_nested_api.php to apply a nested
 * curriculum to courses 101+ in the database.
 * Version: 15:45 (Force Refresh)
 */
if (!defined('MOODLE_INTERNAL')) {
    define('CLI_SCRIPT', false);
    require(__DIR__ . '/config.php');
}
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
// Ensure module libraries are loaded for add_instance calls
require_once($CFG->dirroot . '/mod/label/lib.php');
require_once($CFG->dirroot . '/mod/page/lib.php');
require_once($CFG->dirroot . '/mod/url/lib.php');
require_once($CFG->dirroot . '/mod/resource/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

global $DB;

if (!CLI_SCRIPT || defined('RUN_BULK_SEED')) {
    global $USER;
    $USER = get_admin();
}

function bulk_update_nested_hierarchy($offset = 1)
{
    global $DB;
    echo "--- [VERSION 14:31: NESTED] ---\n";
    echo "--- [TIMESTAMP: Mon Apr 6 15:20:00 WAT 2026] ---\n";
    echo "Fetching courses starting from offset $offset...\n";
    $courses = $DB->get_records('course', [], 'id ASC', 'id, fullname, shortname', $offset, 1000);

    if (empty($courses)) {
        echo "No courses found to update.\n";
        return;
    }

    $tree = [
        (object) [
            'name' => 'Module 1: Engine Foundation',
            'items' => [
                (object) [
                    'type' => 'page',
                    'name' => 'Introduction Page',
                    'indent' => 0,
                    'content' => 'Welcome to the core.'
                ],
                (object) [
                    'type' => 'subsection',
                    'name' => 'Deep Dive: Core Mechanics',
                    'items' => [
                        (object) [
                            'type' => 'url',
                            'name' => 'External Architecture Reference',
                            'url' => 'https://moodle.org'
                        ],
                        (object) [
                            'type' => 'page',
                            'name' => 'Internal Matrix Documentation',
                            'content' => 'Sub-layer page active.'
                        ]
                    ]
                ]
            ]
        ]
    ];
    $json_tree = json_encode($tree);

    foreach ($courses as $course) {
        if ($course->id == SITEID)
            continue;
        echo "Updating Course [{$course->id}] {$course->fullname} (Nested)...\n";

        // Mock API request bridge logic manually to avoid full inclusion for performance
        // This is the core of sync_course_structure action
        foreach ($tree as $index => $node) {
            $sectionnum = $index + 1;
            course_create_sections_if_missing($course->id, [(int) $sectionnum]);
            rebuild_course_cache($course->id, true);
            $modinfo = get_fast_modinfo($course->id);
            if (!$modinfo || !is_object($modinfo)) {
                throw new \moodle_exception("Failed to load modinfo for Course {$course->id}");
            }
            $section = $modinfo->get_section_info($sectionnum);

            if ($section && is_object($section)) {
                $DB->set_field('course_sections', 'name', $node->name, ['id' => $section->id]);
                $new_sequence = [];
                foreach ($node->items as $item) {
                    if (($item->type ?? '') === 'subsection') {
                        $maxsec = $DB->get_field_sql("SELECT MAX(section) FROM {course_sections} WHERE course = ?", [$course->id]);
                        $sub_sec_num = (int) $maxsec + 1;
                        course_create_sections_if_missing($course->id, [$sub_sec_num]);
                        rebuild_course_cache($course->id, true);
                        $modinfo = get_fast_modinfo($course->id, 0, true); // Force refresh

                        // Moodle 5.1 compatibility: pivot from 'label' to 'text' if needed
                        $modname_to_use = 'text';
                        $modrec = $DB->get_record('modules', ['name' => 'text']);
                        if (!$modrec) {
                            $modrec = $DB->get_record('modules', ['name' => 'label']);
                            $modname_to_use = 'label';
                        }
                        
                        if (!$modrec) {
                             $fallback = $DB->get_records('modules', [], '', 'id, name');
                             $names = array_map(function($m) { return $m->name; }, $fallback);
                             throw new \moodle_exception("Neither 'text' nor 'label' module found. Available: " . implode(',', $names));
                        }
                        
                        $actual_mod_id = (int)$modrec->id;
                        
                        $minfo = (object)[
                            'modulename' => $modname_to_use, 
                            'module'     => $actual_mod_id, 
                            'course'     => (int)$course->id,
                            'section'    => (int)$sectionnum, 
                            'name'       => $item->name,
                            'intro'      => '<!-- subsection -->', 
                            'introformat' => FORMAT_HTML, 
                            'visible'    => 1
                        ];

                        try {
                            $anchor_cm = add_moduleinfo($minfo, $course);
                        } catch (Exception $e) {
                            echo "--- DB ERROR IN SEEDER ---\n";
                            echo $e->getMessage() . "\n";
                            if (property_exists($e, 'debuginfo'))
                                echo "DEBUG: " . $e->debuginfo . "\n";
                            throw $e;
                        }
                        if ($anchor_cm && isset($anchor_cm->coursemodule)) {
                            $new_sequence[] = $anchor_cm->coursemodule;
                            $delegated = (object) [
                                "course" => $course->id,
                                "section" => $sub_sec_num,
                                "name" => $item->name,
                                "summary" => "",
                                "summaryformat" => FORMAT_HTML,
                                "sequence" => "",
                                "visible" => 1,
                                "component" => "core_subsection",
                                "itemid" => $anchor_cm->coursemodule
                            ];
                            $dsid = $DB->insert_record('course_sections', $delegated);
                            $sub_seq = [];
                            foreach ($item->items as $subitem) {
                                $modname = $subitem->type ?? 'label';
                                if ($modname === 'h5p')
                                    $modname = 'h5pactivity';
                                $submodrec = $DB->get_record('modules', ['name' => $modname]);
                                $actual_submod_id = ($submodrec && isset($submodrec->id)) ? (int) $submodrec->id : null;
                                if (!$actual_submod_id)
                                    continue;

                                $sminfo = (object) [
                                    'modulename' => $modname,
                                    'module' => $actual_submod_id,
                                    'course' => (int) $course->id,
                                    'section' => $sub_sec_num,
                                    'name' => $subitem->name,
                                    'visible' => isset($subitem->visible) ? (int) $subitem->visible : 1,
                                    'intro' => $subitem->content ?? '',
                                    'introformat' => FORMAT_HTML
                                ];
                                if ($modname === 'url') {
                                    $sminfo->externalurl = $subitem->url ?? 'http://';
                                    $sminfo->display = 0;
                                }
                                if ($modname === 'page') {
                                    $sminfo->content = $subitem->content ?? '';
                                    $sminfo->contentformat = FORMAT_HTML;
                                }
                                $new_subcm = add_moduleinfo($sminfo, $course);
                                if ($new_subcm && isset($new_subcm->coursemodule))
                                    $sub_seq[] = $new_subcm->coursemodule;
                            }
                            $DB->set_field('course_sections', 'sequence', implode(',', $sub_seq), ['id' => $dsid]);
                        }
                        continue;
                    }

                    // Standard item in nested tree
                    $modname = $item->type ?? 'label';
                    $modrec = $DB->get_record('modules', ['name' => $modname]);
                    if (!$modrec) {
                        $modrec = $DB->get_record_sql("SELECT id FROM {modules} WHERE " . $DB->sql_compare_text('name') . " = ?", [$modname]);
                    }
                    if (!$modrec)
                        continue;
                    $minfo = (object) [
                        'modulename' => $modname,
                        'module' => $modrec->id,
                        'course' => $course->id,
                        'section' => $sectionnum,
                        'name' => $item->name,
                        'visible' => isset($item->visible) ? (int) $item->visible : 1,
                        'intro' => $item->content ?? '',
                        'introformat' => FORMAT_HTML
                    ];
                    if ($modname === 'url') {
                        $minfo->externalurl = $item->url ?? 'http://';
                        $minfo->display = 0;
                    }
                    if ($modname === 'page') {
                        $minfo->content = $item->content ?? '';
                        $minfo->contentformat = FORMAT_HTML;
                    }

                    try {
                        $n_cm = add_moduleinfo($minfo, $course);
                        if ($n_cm && isset($n_cm->coursemodule))
                            $new_sequence[] = $n_cm->coursemodule;
                    } catch (Exception $e) { /* ignore */
                    }
                }
                $DB->set_field('course_sections', 'sequence', implode(',', $new_sequence), ['id' => $section->id]);
            }
        }
        rebuild_course_cache($course->id, true);
        echo "Nested Hierarchy Synchronized for Course ID [{$course->id}].\n";
    }
    echo "Bulk Nested Integration Complete.\n";
}

if (PHP_SAPI === 'cli' || defined('RUN_BULK_SEED')) {
    bulk_update_nested_hierarchy(100);
}
// Cache bust Mon Apr  6 15:16:45 WAT 2026
