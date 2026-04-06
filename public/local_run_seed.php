<?php
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/config.php');
/**
 * Remote Seed Trigger Endpoint
 *
 * Allows triggering the full seeding pipeline via an authenticated HTTP call.
 * Used when the background seeder in entrypoint.sh fails to complete
 * (e.g. container recycled on Render free plan before seeding finishes).
 *
 * Usage:
 *   curl -X POST "https://your-render-url/run_seed.php" \
 *        -H "X-Seed-Token: lumina-seed-2026" \
 *        -d "run=all"
 *
 * To seed only specific steps:
 *   -d "run=categories"
 *   -d "run=cohorts"
 *   -d "run=courses"     ← the big one (500 courses)
 *   -d "run=grades"
 */

// ─── Security Gate ────────────────────────────────────────────────────────────
// Change this token before deploying! Set SEED_SECRET in Render env vars.
$expected_token = getenv('SEED_SECRET') ?: 'lumina-seed-2026';
$provided_token = $_SERVER['HTTP_X_SEED_TOKEN'] ?? '';

if ($provided_token !== $expected_token) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden: invalid or missing X-Seed-Token header.']);
    exit;
}

// ─── Only allow POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method Not Allowed. Use POST.']);
    exit;
}

$run = $_POST['run'] ?? 'master';
$valid_steps = ['all', 'master', 'categories', 'cohorts', 'courses', 'grades', 'rbac', 'curriculum_flat', 'curriculum_nested'];

if (!in_array($run, $valid_steps)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => "Invalid step '$run'. Valid: " . implode(', ', $valid_steps)]);
    exit;
}

// ─── Map step names to script files ──────────────────────────────────────────
$scripts = [
    'master'     => __DIR__ . '/local_seed_master.php',
    'categories' => __DIR__ . '/local_seed_categories.php',
    'cohorts'    => __DIR__ . '/local_seed_cohorts.php',
    'courses'    => __DIR__ . '/local_seed_moodle.php',
    'grades'     => __DIR__ . '/local_seed_grades_messages.php',
    'rbac'       => __DIR__ . '/local_seed_rbac.php',
    'curriculum_flat' => __DIR__ . '/local_seed_curriculum_flat.php',
    'curriculum_nested' => __DIR__ . '/local_seed_curriculum_nested.php',
];

if ($run === 'all') {
    $to_run = $scripts;
} else {
    $to_run = [$run => $scripts[$run]];
}

// ─── Stream output back to caller ─────────────────────────────────────────────
// Disable output buffering so the client sees live progress
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
set_time_limit(0);   // allow long-running seeder
ignore_user_abort(true);
define('RUN_BULK_SEED', true); // Signal to batch scripts to execute on inclusion

header('Content-Type: text/plain; charset=UTF-8');
header('X-Accel-Buffering: no'); // disable Nginx buffering if present
header('Cache-Control: no-cache');

if (ob_get_level()) {
    ob_end_flush();
}

echo "=== LUMINA SEED TRIGGER: " . strtoupper($run) . " ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";
flush();

$overall_success = true;

foreach ($to_run as $step => $script_path) {
    echo "--- Running: $step ---\n";
    flush();

    if (!file_exists($script_path)) {
        echo "SKIP: Script not found at $script_path\n\n";
        flush();
        continue;
    }

    // Run via direct inclusion (safe now that CLI_SCRIPT is removed)
    try {
        ob_start();
        include($script_path);
        echo ob_get_clean();
        echo "\n--- $step finished (Success) ---\n\n";
    } catch (Exception $e) {
        $overall_success = false;
        echo "EXCEPTION in $step: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n\n";
        flush();
    } catch (Error $e) {
        $overall_success = false;
        echo "CRITICAL ERROR in $step: " . $e->getMessage() . "\n";
        echo "Line: " . $e->getLine() . " in " . $e->getFile() . "\n\n";
        flush();
    }
    flush();
}

echo "=== SEEDING " . ($overall_success ? "COMPLETE ✓" : "DONE WITH ERRORS ✗") . " ===\n";
echo "Finished: " . date('Y-m-d H:i:s') . "\n";
flush();
