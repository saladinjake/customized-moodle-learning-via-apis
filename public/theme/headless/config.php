<?php

defined('MOODLE_INTERNAL') || die();

$THEME->name = 'headless';
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->parents = ['boost']; // Fallback to boost as parent for renderer compatibility
$THEME->enable_dock = false;

// Override renderers
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
