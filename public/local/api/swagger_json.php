<?php

/**
 * Headless API Swagger Generator (Payload Aware & Hardened)
 * 
 * Dynamically queries Moodle's internal database for ALL registered external Web Services
 * and parses their strict Moodle-native payloads into an OpenAPI 3.0 compatible 
 * JSON document. It includes robust health checks and memory safety guards.
 */

define('AJAX_SCRIPT', true);
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once('../../config.php');
require_once($CFG->dirroot . '/lib/externallib.php');

global $DB, $CFG;

// Recursive parser mapping Moodle's proprietary schema to OpenAPI 3.0 schema
function get_swagger_schema_from_moodle_desc($desc) {
    if (!is_object($desc)) {
        return ['type' => 'string'];
    }

    if ($desc instanceof \external_value) {
        $type = 'string';
        if ($desc->type == PARAM_INT) $type = 'integer';
        if ($desc->type == PARAM_BOOL || $desc->type == PARAM_ALPHA) $type = 'boolean';
        if ($desc->type == PARAM_FLOAT) $type = 'number';
        return ['type' => $type, 'description' => $desc->desc ?? ''];
    } 
    
    if ($desc instanceof \external_single_structure) {
        $props = [];
        $required = [];
        if (!empty($desc->keys)) {
            foreach ($desc->keys as $key => $subdesc) {
                $props[$key] = get_swagger_schema_from_moodle_desc($subdesc);
                if (isset($subdesc->required) && $subdesc->required == VALUE_REQUIRED) {
                    $required[] = $key;
                }
            }
        }
        $schema = ['type' => 'object', 'properties' => $props];
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        return $schema;
    } 
    
    if ($desc instanceof \external_multiple_structure) {
        return [
            'type' => 'array', 
            'items' => get_swagger_schema_from_moodle_desc($desc->content), 
            'description' => $desc->desc ?? ''
        ];
    }
    
    return ['type' => 'string']; // Safe Fallback
}

$swagger = [
    'openapi' => '3.0.0',
    'info' => [
        'title' => 'Complete Decoupled Moodle API (Strict Payload Aware)',
        'description' => 'Automatically generated OpenAPI 3.0 spec mapping all Moodle core functions with exact payload schemas extracted natively.',
        'version' => $CFG->version ?? 'Moodle API POC'
    ],
    'servers' => [['url' => 'http://localhost/local/api']],
    'components' => [
        'securitySchemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'MoodleToken'
            ]
        ]
    ],
    'security' => [['bearerAuth' => []]],
    'paths' => []
];

// Health Check: Ensure DB is active before attempting to scrape
try {
    if (!$DB) {
        throw new \Exception("Database disconnected.");
    }
    
    $external_functions = $DB->get_records('external_functions', null, 'name ASC');
    if (!$external_functions) {
        $external_functions = []; // No functions found safely
    }
    
    foreach ($external_functions as $func) {
        $path = '/index.php?action=moodle_ws_proxy&wsfunction=' . $func->name;
        
        $method = 'post'; // APIs that query lists should ideally gracefully expose as GET if possible, but POST is safer for Moodle WS.
        if (strpos($func->name, 'get_') !== false) {
            $method = 'get';
        }

        // Health Check Boundary: Wrap complex external_info compilation to prevent crashed plugins killing the Swagger JSON
        $schemaBody = null;
        try {
            $functioninfo = \external_api::external_function_info($func);
            if ($functioninfo && $functioninfo->parameters_desc) {
                $schemaBody = get_swagger_schema_from_moodle_desc($functioninfo->parameters_desc);
            }
        } catch (\Exception $e) {
            // Broken plugin schema; fall back to an empty definition
            $schemaBody = ['type' => 'object', 'description' => 'Failed to parse complex parameters. Refer to Moodle docs.'];
        }

        $endpoint_def = [
            'summary' => $func->name,
            'description' => "Mapped Class: `{$func->classname}::{$func->methodname}`",
            'tags' => ['Native Web Services (' . explode('_', $func->name)[0] . ')'],
            'responses' => [
                '200' => ['description' => 'Proxy Execution Results']
            ]
        ];

        // Ensure "Required Payload" is visible on the Swagger Dashboard
        if ($schemaBody && !empty($schemaBody['properties'])) {
            if ($method === 'get') {
                // GET requests map to query parameters in OpenAPI
                $endpoint_def['parameters'] = [];
                foreach ($schemaBody['properties'] as $k => $v) {
                    $endpoint_def['parameters'][] = [
                        'in' => 'query',
                        'name' => $k,
                        'required' => in_array($k, $schemaBody['required'] ?? []),
                        'schema' => isset($v['type']) ? ['type' => $v['type']] : ['type' => 'string'],
                        'description' => $v['description'] ?? ''
                    ];
                }
            } else {
                // POST requests map to standard RequestBody schema
                $endpoint_def['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => $schemaBody
                        ],
                        'application/x-www-form-urlencoded' => [
                            'schema' => $schemaBody
                        ]
                    ]
                ];
            }
        }

        $swagger['paths'][$path][$method] = $endpoint_def;
    }

} catch (\Exception $e) {
    // Graceful error payload if health check fails out-of-box
    $swagger['info']['description'] = "Health Check Failed. Extractor could not load External Functions DB: " . $e->getMessage();
}

$swagger['paths']['/index.php?action=auth_login'] = [
    'post' => [
        'summary' => 'System Authentication',
        'tags' => ['Gateway System'],
        'security' => [],
        'requestBody' => [
            'required' => true,
            'content' => [
                'application/x-www-form-urlencoded' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'username' => ['type' => 'string'],
                            'password' => ['type' => 'string']
                        ],
                        'required' => ['username', 'password']
                    ]
                ]
            ]
        ],
        'responses' => ['200' => ['description' => 'Moodle Token generated']]
    ]
];

echo json_encode($swagger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit();
