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
 * MCP educational access policy provider.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mcp\mcp;

use context;
use local_mcp\extension\access_policy_interface;

/** Restrict educational accounts while preserving site administrative MCP access. */
final class access_policy implements access_policy_interface {
    private function restricted(int $userid): bool {
        return !is_siteadmin($userid)
            && !has_capability('moodle/site:config', \context_system::instance(), $userid);
    }

    public function tool_visible(int $userid, string $tool, bool $write): bool {
        return !$this->restricted($userid)
            || \block_mcp\security\policy::tool_visible($userid, $tool, $write);
    }

    public function authorize_tool(int $userid, string $tool, bool $write, context $context, array $args): void {
        if ($this->restricted($userid)) {
            \block_mcp\security\policy::authorize_tool($userid, $tool, $write, $context, $args);
        }
    }
}
