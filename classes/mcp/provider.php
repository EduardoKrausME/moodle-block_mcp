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
 * Auto-discovered MCP provider.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace block_mcp\mcp;

use local_mcp\extension\read_provider_interface;
use block_mcp\read\tool\get_study_content;
use block_mcp\read\tool\get_my_tasks;
use block_mcp\read\tool\get_my_grades;

/** Auto-discovered provider for educational READ tools. */
final class provider implements read_provider_interface {
    /** Return tools implemented by this Moodle block. */
    public function get_read_tools(): array {
        return [new get_study_content(), new get_my_tasks(), new get_my_grades()];
    }
}
