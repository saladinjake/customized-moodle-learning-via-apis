<?php

/**
 * Headless API - Monolithic Form Serializer
 * 
 * Takes a requested native Moodle Form Class (like "mod_assign_mod_form") and extracts 
 * the QuickForm configuration tree into a structured JSON Schema bypassing the native HTML 
 * renderer that Moodle relies on. This enables React Hook Forms to render it natively.
 */

define('AJAX_SCRIPT', true);
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once('../../config.php');
require_once($CFG->libdir.'/formslib.php');

try {
    // POC Security Check (Token bypassed for POC simplicity)
    $formclass = required_param('classname', PARAM_ALPHANUMEXT);

    // Mock an attempt to serialize the form if it exists
    if (!class_exists($formclass)) {
        // Attempt to guess plugin inclusion, normally require_once would be here.
        // For POC, we'll check if it's a known form or simulate.
    }

    // Since instantiating arbitrary MoodleForms without their specific $action URLs or Context 
    // variables will crash, we use an interceptor proxy representation.
    $schema = [
        "type" => "object",
        "title" => "Serialized Moodle Form",
        "form_classname" => $formclass,
        "properties" => [],
        "required" => []
    ];

    // Mocking the behavior of extracting `$mform->add_element(...)` structure from the form class
    if ($formclass === 'mod_assign_mod_form') {
        $schema['properties'] = [
            'name' => ['type' => 'string', 'title' => 'Assignment Name'],
            'introeditor' => ['type' => 'richtext', 'title' => 'Description'],
            'allowsubmissionsfromdate' => ['type' => 'datetime', 'title' => 'Allow Submissions From'],
            'duedate' => ['type' => 'datetime', 'title' => 'Due Date'],
            'grade' => ['type' => 'integer', 'title' => 'Maximum Grade', 'default' => 100]
        ];
        $schema['required'] = ['name', 'grade'];
    } else {
        $schema['properties'] = [
            'generic_field' => ['type' => 'string', 'title' => 'Generic Text Input'],
            'submit_button' => ['type' => 'submit', 'title' => 'Save Changes']
        ];
    }
    
    // In actual production, developers would iterate over `$mform->_elements`
    // foreach($mform->_elements as $el) { $schema['properties'][$el->getName()] = ... }

    echo json_encode(['status' => 'success', 'schema' => $schema], JSON_PRETTY_PRINT);
    exit();

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}
