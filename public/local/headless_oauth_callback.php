<?php
/**
 * Headless OAuth2 Callback Handler
 *
 * After Moodle completes the OAuth2 dance with Google (or any provider),
 * it redirects here via the `wantsurl` parameter set when the login started.
 *
 * At this point $USER is already populated by Moodle's auth_oauth2 plugin.
 * We generate a token and redirect back to the React SPA with it in the URL.
 */

define('AJAX_SCRIPT', false);
define('NO_MOODLE_COOKIES', false); // we need the Moodle session

require_once(__DIR__ . '/../../config.php');

global $USER, $CFG, $DB;

// Load .env for dynamic frontend redirection
$FRONTEND_URL = 'http://localhost:5173';
$env_path = $CFG->dirroot . '/.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), 'FRONTEND_REDIRECT_URL=') === 0) {
            $parts = explode('=', $line, 2);
            if (isset($parts[1])) $FRONTEND_URL = trim($parts[1]);
        }
    }
}

// If no user is logged in the OAuth2 callback failed
if (!isloggedin() || isguestuser()) {
    $error = urlencode('Authentication failed or was cancelled. Please try again.');
    header("Location: {$FRONTEND_URL}?oauth_error={$error}");
    exit;
}

// Build user payload (same shape as auth_login response)
$userData = [
    'user_id'  => (string) $USER->id,
    'username' => $USER->username,
    'fullname' => fullname($USER),
    'email'    => $USER->email,
    'is_admin' => is_siteadmin($USER->id),
    // TODO: replace with a real Moodle web-service token in production
    'token'    => 'POC-TOKEN-123',
];

$encodedUser = urlencode(json_encode($userData));
$token       = urlencode($userData['token']);

header("Location: {$FRONTEND_URL}?oauth_token={$token}&oauth_user={$encodedUser}");
exit;
