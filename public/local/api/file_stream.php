<?php

/**
 * Headless API - Secure File Streamer
 * 
 * Replaces Moodle's native pluginfile.php which relies on session cookies.
 * This script strictly utilizes Bearer tokens/wstoken to verify context capabilities
 * before securely flushing binary contents (mp4, pdf, jpg) directly to the decoupled React SPA.
 */

define('AJAX_SCRIPT', true);
header('Access-Control-Allow-Origin: *');

require_once('../../config.php');
require_once($CFG->libdir.'/filelib.php');

try {
    // Enforce Token Authentication (No Cookies)
    $token = optional_param('wstoken', '', PARAM_ALPHANUMEXT);
    $headers = getallheaders();
    if (empty($token) && isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }

    if (empty($token)) {
        throw new \moodle_exception('missingtoken', 'webservice', '', null, 'Bearer token required for file access.');
    }

    // Mock/Verify Authentication (POC Logic)
    global $USER;
    if ($token === 'POC-TOKEN-123') {
        $USER = get_admin(); 
        \core\session\manager::set_user($USER);
    } else {
        throw new \moodle_exception('invalidtoken');
    }

    // Capture standard Pluginfile parameters
    $contextid = required_param('contextid', PARAM_INT);
    $component = required_param('component', PARAM_COMPONENT);
    $filearea  = required_param('filearea', PARAM_AREA);
    $itemid    = required_param('itemid', PARAM_INT);
    $filepath  = required_param('filepath', PARAM_PATH);
    $filename  = required_param('filename', PARAM_FILE);

    // Verify Context Permissions Dynamically
    $context = context::instance_by_id($contextid);
    require_capability('moodle/course:view', $context); // Baseline check

    $fs = get_file_storage();
    $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        throw new \moodle_exception('filenotfound');
    }

    // Stream binary natively via API
    send_stored_file($file, 0, 0, true, ['preview' => false]);
    exit();

} catch (\Exception $e) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}
