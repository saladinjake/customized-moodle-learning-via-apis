<?php

/**
 * Headless API - Monolith Plugin HTML Auditor (CLI)
 * 
 * Scans Moodle /mod and /blocks directories for strict compliance with the 
 * API-First headless approach. Flagging plugins that hardcode echo "<div>", breaking 
 * the Headless Interceptor from packaging pure `$context` JSON arrays.
 */

define('CLI_SCRIPT', true);
require_once('../../../../config.php');

echo "===========================================\n";
echo " Moodle API-First Compliance Scanner\n";
echo "===========================================\n";

$directories_to_scan = [
    $CFG->dirroot . '/mod',
    $CFG->dirroot . '/blocks',
    $CFG->dirroot . '/local'
];

$violations_found = 0;

foreach ($directories_to_scan as $dir) {
    if (!is_dir($dir)) continue;
    
    // PHP implementation of a recursive directory iterator grabbing .php files
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }

        $filepath = $file->getPathname();
        
        // Skip language strings and settings matrices as they don't echo UI natively.
        if (strpos($filepath, '/lang/') !== false || strpos($filepath, 'settings.php') !== false) {
            continue;
        }

        $contents = file_get_contents($filepath);
        if ($contents === false) continue;

        // Scans for the egregious legacy HTML output patterns:
        // Echoing structural block tags directly out instead of via $OUTPUT->render_from_template
        if (preg_match('/echo\s+[\'"]\s*<(div|span|table|form|iframe|script).*?>/i', $contents, $matches)) {
            echo "[VIOLATION] Hardcoded HTML detected -> " . str_replace($CFG->dirroot, '', $filepath) . "\n";
            echo "   Line Snippet: " . substr($matches[0], 0, 80) . "...\n";
            echo "   FIX: Must refactor to build an object and call render_from_template().\n\n";
            $violations_found++;
        }
    }
}

echo "===========================================\n";
if ($violations_found === 0) {
    echo "SUCCESS: No legacy HTML violations found. Moodle is fully Headless JSON compliant!\n";
} else {
    echo "FAILED: $violations_found Third-Party plugin violations found preventing complete API-First rendering.\n";
}
exit();
