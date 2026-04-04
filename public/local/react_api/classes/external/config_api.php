<?php
// This file is part of Moodle - http://moodle.org/

/**
 * External config API for the React SPA.
 *
 * @package    local_react_api
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_react_api\external;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

/**
 * Config API class definition.
 */
class config_api extends external_api {

    /**
     * Parameter definition for get_initial_data
     *
     * @return external_function_parameters
     */
    public static function get_initial_data_parameters() {
        return new external_function_parameters(
            array(
                // No parameters required for this example configuration fetch.
            )
        );
    }

    /**
     * Returns configuration data needed to bootstrap the React frontend.
     *
     * @return array
     */
    public static function get_initial_data() {
        global $CFG, $SITE, $USER;

        // Ensure the API is being accessed contextually via the system context.
        $context = \context_system::instance();
        self::validate_context($context);

        // This is a sample response mapped for the React frontend.
        // E.g. To replace templates like setting_configcolourpicker.mustache,
        // we might return theme settings from $CFG here.
        return array(
            'sitename' => $SITE->fullname,
            'siteurl' => $CFG->wwwroot,
            'user' => array(
                'id' => $USER->id,
                'username' => $USER->username,
                'firstname' => $USER->firstname,
                'lastname' => $USER->lastname,
                'email' => $USER->email
            ),
            'theme_settings' => array(
                array(
                    'name' => 'primary_color',
                    'value' => !empty($CFG->theme_primary_color) ? $CFG->theme_primary_color : '#0f6cbf'
                ),
            )
        );
    }

    /**
     * Return structure for get_initial_data
     *
     * @return external_single_structure
     */
    public static function get_initial_data_returns() {
        return new external_single_structure(
            array(
                'sitename' => new external_value(PARAM_TEXT, 'The full name of the Moodle site'),
                'siteurl'  => new external_value(PARAM_URL, 'The URL of the Moodle site'),
                'user'     => new external_single_structure(
                    array(
                        'id'        => new external_value(PARAM_INT, 'User ID'),
                        'username'  => new external_value(PARAM_RAW, 'Username'),
                        'firstname' => new external_value(PARAM_NOTAGS, 'First name'),
                        'lastname'  => new external_value(PARAM_NOTAGS, 'Last name'),
                        'email'     => new external_value(PARAM_EMAIL, 'Email address')
                    ), 'Authenticated User details'
                ),
                'theme_settings' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name'  => new external_value(PARAM_ALPHANUMEXT, 'Setting name (e.g. primary_color)'),
                            'value' => new external_value(PARAM_RAW, 'Setting value (e.g. Hex code)'),
                        )
                    ),
                    'Array of configurable theme variables, acting as the data behind color picker components.',
                    VALUE_OPTIONAL
                )
            )
        );
    }
}
