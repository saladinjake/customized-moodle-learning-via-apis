<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/public/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

// 1. Mock Course Creation
$course_data = (object)[
    'fullname' => 'Test High-Fidelity Course',
    'shortname' => 'TEST-HF-' . time(),
    'category' => 1,
    'summary' => 'Testing hierarchical provisioning.',
    'format' => 'topics',
    'numsections' => 2,
    'visible' => 0
];

echo "Provisioning Base Course...\n";
$course = create_course($course_data);
if (!$course) die("Course Provisioning Failed.\n");
echo "Course ID: {$course->id}\n";

// 2. Mock Hierarchical Tree
$tree = [
    (object)[
        'name' => 'Module 1: Foundations',
        'items' => [
            (object)[ 'type' => 'label', 'name' => 'Unit 1.1: Introduction', 'content' => '<p>Welcome</p>' ],
            (object)[ 'type' => 'url', 'name' => 'Video Link', 'url' => 'https://youtube.com/watch?v=123' ]
        ]
    ]
];

echo "Tree Structure Trace:\n";
var_dump($tree);
echo "Tree Count: " . count($tree) . "\n";

echo "Synchronizing Hierarchical Matrix...\n";
foreach ($tree as $index => $node) {
    $sectionnum = $index + 1;
    echo "Creating/Getting Section [{$sectionnum}]...\n";
    course_create_sections_if_missing($course->id, [(int)$sectionnum]);
    $modinfo = get_fast_modinfo($course);
    $section = $modinfo->get_section_info($sectionnum);
    if ($section && is_object($section)) {
        echo "Section ID [{$section->id}] confirmed. Renaming to [{$node->name}]...\n";
        $DB->set_field('course_sections', 'name', $node->name, ['id' => $section->id]);
        
        $new_sequence = [];
        echo "Provisioning ".count($node->items)." items...\n";
        foreach ($node->items as $item) {
            $modrec = $DB->get_record('modules', ['name' => $item->type]);
            if (!$modrec) {
                echo "Critical: Module type [{$item->type}] NOT FOUND in platform registry.\n";
                continue;
            }
            
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
                $minfo->display = 0; // Default
            }

            try {
                // In some Moodle versions, add_moduleinfo requires a form object or specific fields.
                // We test it here.
                echo "Deploying Unit: {$item->name} ({$item->type})...\n";
                $new_cm = add_moduleinfo($minfo, $course);
                if ($new_cm) $new_sequence[] = $new_cm->coursemodule;
            } catch (Exception $e) {
                echo "Unit Deployment Failed: " . $e->getMessage() . "\n";
            }
        }
        $DB->set_field('course_sections', 'sequence', implode(',', $new_sequence), ['id' => $section->id]);
    }
}

rebuild_course_cache($course->id);
echo "Hierarchy Synchronized Successfully.\n";
echo "Final Course Registry Check (Course ID: {$course->id})\n";
$modules = $DB->get_records('course_modules', ['course' => $course->id]);
echo "Provisioned Modules: " . count($modules) . "\n";
foreach ($modules as $m) {
    $modname = $DB->get_field('modules', 'name', ['id' => $m->module]);
    echo " - CMID: {$m->id}, Type: {$modname}, Section: {$m->section}\n";
}
echo "Verification Sequence Complete.\n";
