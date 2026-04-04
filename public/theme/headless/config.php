<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Headless Theme overrides to force JSON output globally.
 *
 * @package    theme_headless
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$THEME->name = 'headless';
// We base this on boost to ensure all core definitions are loaded safely.
$THEME->parents = ['boost'];
$THEME->enable_dock = false;

// The key to our approach: we force Moodle to use our custom renderer factory.
// This allows us to intercept all HTML views.
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
