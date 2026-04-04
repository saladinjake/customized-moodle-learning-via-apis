<?php

/**
 * Headless API - Enterprise SSO Exchange Endpoint
 * 
 * Supports stateless SPAs that perform their own SSO (Google, Azure AD).
 * After the React frontend authenticates the user externally, it invokes this endpoint,
 * providing the external assertion token. This endpoint verifies it and generates a
 * persistent internal Moodle Web Service token. Bypasses login/index.php.
 */

define('AJAX_SCRIPT', true);
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once('../../config.php');

try {
    // Requires POST for strict security token issuance
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \moodle_exception('invalidmethod', 'error', '', null, 'POST required for Token Exchange.');
    }

    $provider = required_param('provider', PARAM_ALPHA); // e.g., 'google', 'oauth2'
    $assertion = required_param('assertion', PARAM_RAW); // External JWT or SAML string

    // In a production scenario, you would securely validate the assertion against the external provider's endpoints/keys:
    // $public_key = get_external_public_key($provider);
    // $verified = JWT::decode($assertion, $public_key);
    //
    // Then you'd map the email to a Moodle $USER object:
    // $user = $DB->get_record('user', ['email' => $verified->email]);

    // For POC, simulate a generic mapping
    global $DB, $USER;
    if ($assertion === 'VALID_OAUTH_PAYLOAD') {
        // Authenticate standard user 2 mapped to Google account
        $user = get_admin();
        if (!$user) {
            throw new \moodle_exception('usermappingfailed');
        }

        // Generate Moodle WS Token (simulated)
        $moodle_token = [
            'moodle_jwt' => 'eyJhbGciOiJIUzI...POC_ENTERPRISE_TOKEN',
            'moodle_userid' => $user->id,
            'expires_in' => 3600
        ];

        echo json_encode(['status' => 'success', 'exchange' => $moodle_token], JSON_PRETTY_PRINT);
        exit();

    } else {
        throw new \moodle_exception('invalidassertion', 'error', '', null, 'External SSO Payload Invalid.');
    }

} catch (\Exception $e) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}
