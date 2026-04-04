<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Renderer overrides for the headless theme.
 *
 * @package    theme_headless
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class theme_headless_core_renderer extends core_renderer {

    /**
     * Intercept the standard page header. Instead of returning HTML, we map the Moodle $PAGE context 
     * to a buffer and prepare to encode it to JSON. We return an empty string so the controller keeps running.
     */
    public function header() {
        global $PAGE, $USER, $SITE;

        if ($PAGE->pagelayout === 'popup' || $PAGE->pagelayout === 'embedded') {
            return ''; // We skip complex modals for now.
        }

        // Send correct headers since we are forcing JSON natively
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        // Capture data safely
        $this->headless_data = [
            'theme' => 'headless',
            'layout' => $PAGE->pagelayout,
            'title' => $PAGE->title,
            'heading' => $PAGE->heading,
            'site' => [
                'fullname' => $SITE->fullname,
                'shortname' => $SITE->shortname
            ],
            'user' => [
                'id' => $USER->id,
                'username' => $USER->username,
                'fullname' => fullname($USER)
            ],
            'contextId' => $PAGE->context->id,
        ];

        // Start an output buffer. We do this to catch any HTML dumped by the page controllers 
        // after the header call, so that our JSON remains perfectly valid and isolated.
        ob_start();
        return '';
    }

    /**
     * Intercept the page footer. We extract the accumulated HTML from the buffer (discarding it or logging it),
     * and finally echo our structured JSON payload before stopping execution.
     */
    public function footer() {
        global $PAGE;

        // Clean the output buffer to completely swallow random Moodle HTML that controllers may have dumped.
        $controller_html = '';
        if (ob_get_level() > 0) {
            $controller_html = ob_get_clean();
        }

        // Technically, some plugins might have tried to throw HTML here. We can include it just in case
        // the React frontend needs to render raw innerHTML of complex plugins, or discard it.
        $this->headless_data['raw_html'] = $controller_html;

        // Generate the final JSON payload.
        echo json_encode($this->headless_data);
        exit;
    }
}
