<?php
/**
 * Headless API Unit Tests
 * 
 * Tests all custom endpoints in /local/api/index.php
 * Run: php test_api.php
 */

define('CLI_SCRIPT', true);

// ─── Config ────────────────────────────────────────────────────────────────
$BASE_URL = 'http://localhost:8000/local/api/index.php';
$TOKEN    = '';  // Will be populated by test_auth_login

// ─── Test Runner ───────────────────────────────────────────────────────────
$pass = 0;
$fail = 0;
$results = [];

function request(string $action, array $params = [], string $method = 'GET', array $body = [], string $token = ''): array {
    global $BASE_URL;
    $url = $BASE_URL . '?action=' . $action;
    if ($method === 'GET' && $params) {
        $url .= '&' . http_build_query($params);
    }

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($raw, true);
    if ($data === null) {
        return ['status' => 'error', 'message' => 'Invalid JSON response: ' . substr($raw, 0, 100), '_http' => $httpCode];
    }
    $data['_http'] = $httpCode;
    return $data;
}

function assert_success(string $label, array $result, callable $extra = null): void {
    global $pass, $fail, $results;
    $ok = ($result['status'] ?? '') === 'success';
    if ($ok && $extra) {
        $ok = $extra($result);
    }
    if ($ok) {
        $pass++;
        $results[] = ['status' => 'PASS', 'label' => $label];
        echo "\033[32m✓\033[0m $label\n";
    } else {
        $fail++;
        $msg = $result['message'] ?? $result['code'] ?? $result['error'] ?? 'unknown';
        $results[] = ['status' => 'FAIL', 'label' => $label, 'msg' => $msg];
        echo "\033[31m✗\033[0m $label  →  $msg\n";
    }
}

// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1m━━━ PUBLIC ENDPOINTS ━━━\033[0m\n";
// ═══════════════════════════════════════════════════════════════════════════

// GET: ping
$r = request('ping');
assert_success('GET  ping', $r, fn($r) => isset($r['data']['version']));

// GET: auth_get_social_providers
$r = request('auth_get_social_providers');
assert_success('GET  auth_get_social_providers', $r, fn($r) => is_array($r['data'] ?? null));

// GET: auth_get_signup_settings
$r = request('auth_get_signup_settings');
assert_success('GET  auth_get_signup_settings', $r);

// POST: auth_login (with known admin credentials)
$r = request('auth_login', [], 'POST', ['username' => 'admin', 'password' => 'Headless@2026']);
assert_success('POST auth_login', $r, function($r) {
    global $TOKEN;
    if (!empty($r['data']['token'])) {
        $TOKEN = $r['data']['token'];
        return true;
    }
    return false;
});

// POST: auth_signup_user (new unique user each run)
$uid = substr(uniqid(), -6);
$r = request('auth_signup_user', [], 'POST', [
    'username'  => 'testunit' . $uid,
    'password'  => 'Unit@12345',
    'email'     => 'testunit' . $uid . '@example.com',
    'firstname' => 'Unit',
    'lastname'  => 'Test',
]);
assert_success('POST auth_signup_user', $r);

// POST: auth_request_password_reset
$r = request('auth_request_password_reset', [], 'POST', ['email' => 'admin@example.com']);
assert_success('POST auth_request_password_reset', $r);

// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1m━━━ STUDENT ENDPOINTS ━━━\033[0m\n";
// ═══════════════════════════════════════════════════════════════════════════

// GET: calendar
$r = request('get_calendar_monthly', ['year' => 2026, 'month' => 4], 'GET', [], $TOKEN);
assert_success('GET  get_calendar_monthly', $r);

// GET: user notes
$r = request('get_user_notes', [], 'GET', [], $TOKEN);
assert_success('GET  get_user_notes', $r);

// GET: moodle_ws_proxy → get enrolled courses
$r = request('moodle_ws_proxy', ['wsfunction' => 'core_enrol_get_users_courses', 'userid' => 2], 'GET', [], $TOKEN);
assert_success('GET  moodle_ws_proxy (enrol_get_users_courses)', $r);

// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1m━━━ INSTRUCTOR ENDPOINTS ━━━\033[0m\n";
// ═══════════════════════════════════════════════════════════════════════════

// GET: questions
$r = request('instructor_get_questions', [], 'GET', [], $TOKEN);
assert_success('GET  instructor_get_questions', $r);

// GET: grade items
$r = request('instructor_get_grade_items', ['courseid' => 2], 'GET', [], $TOKEN);
assert_success('GET  instructor_get_grade_items', $r);

// GET: progress report
$r = request('instructor_get_progress_report', ['courseid' => 2], 'GET', [], $TOKEN);
assert_success('GET  instructor_get_progress_report', $r);

// GET: user files
$r = request('instructor_get_user_files', [], 'GET', [], $TOKEN);
assert_success('GET  instructor_get_user_files', $r);

// GET: groups
$r = request('instructor_manage_groups', ['courseid' => 2, 'groupaction' => 'get'], 'GET', [], $TOKEN);
assert_success('GET  instructor_manage_groups (get)', $r);

// GET: participants
$r = request('instructor_get_course_participants', ['courseid' => 2], 'GET', [], $TOKEN);
assert_success('GET  instructor_get_course_participants', $r);

// POST: create question
$r = request('instructor_create_question', [], 'POST', [
    'name'  => 'Unit Test Q',
    'text'  => 'Is this a unit test?',
    'qtype' => 'truefalse',
], $TOKEN);
assert_success('POST instructor_create_question', $r);

// POST: add section
$r = request('instructor_manage_sections', [], 'POST', [
    'courseid'      => 2,
    'sectionaction' => 'add',
    'sectionnum'    => 0,
], $TOKEN);
assert_success('POST instructor_manage_sections (add)', $r);

// POST: rename section
$r = request('instructor_manage_sections', [], 'POST', [
    'courseid'      => 2,
    'sectionaction' => 'rename',
    'sectionnum'    => 1,
    'name'          => 'Unit Test Section',
], $TOKEN);
assert_success('POST instructor_manage_sections (rename)', $r);

// POST: create group
$r = request('instructor_manage_groups', [], 'POST', [
    'courseid'    => 2,
    'groupaction' => 'create',
    'groups'      => [['courseid' => 2, 'name' => 'Unit Group ' . $uid, 'description' => 'unit test']],
], $TOKEN);
assert_success('POST instructor_manage_groups (create)', $r);

// POST: update grade item weight
$r = request('instructor_update_grade_item', [], 'POST', [
    'itemid'              => 1,
    'aggregation_weight'  => 30,
], $TOKEN);
assert_success('POST instructor_update_grade_item', $r);

// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1m━━━ ADMIN ENDPOINTS ━━━\033[0m\n";
// ═══════════════════════════════════════════════════════════════════════════

// GET: plugins
$r = request('admin_get_plugins', [], 'GET', [], $TOKEN);
assert_success('GET  admin_get_plugins', $r);

// POST: toggle plugin
$r = request('admin_set_plugin_status', [], 'POST', [
    'plugin'  => 'mod_assign',
    'enabled' => 1,
], $TOKEN);
assert_success('POST admin_set_plugin_status', $r);

// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
$total = $pass + $fail;
$color = $fail === 0 ? "\033[32m" : "\033[31m";
echo " {$color}RESULTS: $pass/$total passed";
if ($fail > 0) echo "  |  $fail FAILED";
echo "\033[0m\n";
echo "\033[1m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";

if ($fail > 0) {
    echo "\033[31m FAILED TESTS:\033[0m\n";
    foreach ($results as $r) {
        if ($r['status'] === 'FAIL') {
            echo "  ✗ {$r['label']}: {$r['msg']}\n";
        }
    }
    echo "\n";
    exit(1);
}
exit(0);
