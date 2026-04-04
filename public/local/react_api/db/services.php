<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Custom web service functions and external services for the React SPA.
 *
 * @package    local_react_api
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Array of custom functions specifically created for the React frontend.
$functions = array(
    'local_react_api_get_initial_data' => array(
        'classname'   => 'local_react_api\external\config_api',
        'methodname'  => 'get_initial_data',
        'classpath'   => '',
        'description' => 'Returns base Moodle configuration strings and settings needed to bootstrap the React SPA.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => array('react_frontend_service'),
    ),
);

// Define an external service that automatically creates a token template for the React frontend,
// bringing together all standard Moodle API calls and our custom ones.
$services = array(
    'react_frontend_service' => array(
        'shortname'   => 'react_frontend_service',
        'name'        => 'React Frontend API Protocol',
        'enabled'     => 1,
        'restrictedusers' => 0,
        'requiredcapability' => 'moodle/site:config',
    )
);
