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
 * Shared educational MCP tool helpers.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace block_mcp\read\tool;

use context;
use context_course;
use local_mcp\read\tool_interface;

/** Shared input schema and course context helpers. */
abstract class base_tool implements tool_interface {
    /** Standard restrictive JSON-schema object. */
    protected function schema(array $properties, array $required): array {
        return ["type" => "object", "properties" => $properties,
            "required" => $required, "additionalProperties" => false];
    }

    /** The default Moodle capability is checked by local_mcp before execution. */
    public function get_required_capability(): string {
        return "moodle/course:view";
    }

    /** Most student tools operate on one course. */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }
}
