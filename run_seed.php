<?php
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

$run = $_POST['run'] ?? 'all';
$valid_steps = ['all', 'categories', 'cohorts', 'courses', 'grades'];

if (!in_array($run, $valid_steps)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => "Invalid step '$run'. Valid: " . implode(', ', $valid_steps)]);
    exit;
}

// ─── Map step names to script files ──────────────────────────────────────────
$scripts = [
    'categories' => __DIR__ . '/seed_categories.php',
    'cohorts'    => __DIR__ . '/seed_cohorts.php',
    'courses'    => __DIR__ . '/seed_moodle.php',
    'grades'     => __DIR__ . '/seed_grades_messages.php',
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

    // Run in a subprocess so each seeder gets its own CLI_SCRIPT define
    $cmd = "php " . escapeshellarg($script_path) . " 2>&1";
    $proc = popen($cmd, 'r');

    if (!$proc) {
        echo "ERROR: Could not start process for $step\n\n";
        $overall_success = false;
        flush();
        continue;
    }

    while (!feof($proc)) {
        $line = fgets($proc, 4096);
        if ($line !== false) {
            echo $line;
            flush();
        }
    }

    $exit_code = pclose($proc);
    echo "\n--- $step finished (exit: $exit_code) ---\n\n";

    if ($exit_code !== 0) {
        $overall_success = false;
    }

    flush();
}

echo "=== SEEDING " . ($overall_success ? "COMPLETE ✓" : "DONE WITH ERRORS ✗") . " ===\n";
echo "Finished: " . date('Y-m-d H:i:s') . "\n";
flush();
