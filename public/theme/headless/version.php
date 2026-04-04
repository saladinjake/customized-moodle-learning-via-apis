<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Headless Theme version mapping.
 */
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2024040100;
$plugin->requires  = 2022041900;
$plugin->component = 'theme_headless';
$plugin->dependencies = [
    'theme_boost' => 2022041900,
];
