<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_rsmax\output;

use core\output\renderable;
use tool_policy\output\page_agreedocs;

defined('MOODLE_INTERNAL') || die();

/**
 * Policy renderer that preserves Moodle's native consent flow and localises its success message.
 *
 * @package    theme_rsmax
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_policy_renderer extends \tool_policy\output\renderer {
    /**
     * Render policy output without changing how Moodle validates or records consent.
     *
     * @param renderable $widget Policy output widget.
     * @return string
     */
    public function render(renderable $widget) {
        if (!$widget instanceof page_agreedocs) {
            return parent::render($widget);
        }

        $data = $widget->export_for_template($this);
        if (!empty($data->messages)) {
            $native = s(get_string('acceptancessavedsucessfully', 'tool_policy'));
            $institutional = s(get_string('consentchoicesaved', 'theme_rsmax'));
            foreach ($data->messages as &$message) {
                $message = str_replace($native, $institutional, $message);
            }
            unset($message);
        }

        return $this->render_from_template('tool_policy/page_agreedocs', $data);
    }
}
