<?php

namespace theme_headless\output;

defined('MOODLE_INTERNAL') || die();

class core_renderer extends \core_renderer {

    /**
     * Intercepts standard Mustache rendering in Moodle and returns the JSON payload instead.
     * This makes Moodle a "headless API" out of the box.
     */
    public function render_from_template($templatename, $context) {
        // Allow the context to export its necessary scalar/array values
        if (is_object($context) && method_exists($context, 'export_for_template')) {
            $context = $context->export_for_template($this);
        }

        // Output as pure JSON
        // Using a custom structure so clients can parse it easily
        $payload = [
            'type' => 'headless_component',
            'component' => $templatename,
            'data' => $context
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Override standard page header to prevent HTML boilerplate
     */
    public function header() {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            // Allow CORS for the separate React app
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
        }
        
        // Return an empty string or a JSON open wrapper if needed, 
        // but typically returning '' stops HTML from leaking.
        return ''; 
    }

    /**
     * Override standard page footer to prevent HTML boilerplate
     */
    public function footer() {
        return '';
    }

    /**
     * Catch-all renderer for anything missing to also return JSON
     */
    public function render(\renderable $widget) {
        $rendermethod = 'render_' . get_class($widget);
        if (method_exists($this, $rendermethod)) {
            return $this->$rendermethod($widget);
        }
        
        // If we can't find a renderer, serialize it.
        return json_encode([
            'type' => 'unrendered_widget',
            'class' => get_class($widget),
            'data' => (array)$widget
        ]);
    }

    public function standard_head_html() {
        return '';
    }

    public function standard_top_of_body_html() {
        return '';
    }

    public function standard_end_of_body_html() {
        return '';
    }
}
