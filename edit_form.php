<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Block instance configuration form.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined("MOODLE_INTERNAL") || die;

/** Course-level settings for which users can access MCP. */
class block_mcp_edit_form extends block_edit_form {
    /** Build the additional instance configuration fields. */
    protected function specific_definition($mform): void {
        $mform->addElement("header", "configheader", get_string("settingsheading", "block_mcp"));
        $mform->addElement("select", "config_audience", get_string("audience", "block_mcp"), [
            "teachers" => get_string("teachers", "block_mcp"),
            "all" => get_string("teachersandstudents", "block_mcp"),
        ]);
        $mform->setDefault("config_audience", "teachers");
        $mform->addElement("advcheckbox", "config_teacherwrite", get_string("teacherwrite", "block_mcp"));
        $mform->setDefault("config_teacherwrite", 0);
        $mform->addHelpButton("config_teacherwrite", "teacherwrite", "block_mcp");
    }
}
