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
 * English language strings.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined("MOODLE_INTERNAL") || die;

$string["accessnote"] = "Custom MCP connections are not available on every ChatGPT plan or workspace.";
$string["audience"] = "Who can connect";
$string["chatgptbutton"] = "Open ChatGPT";
$string["mcp:addinstance"] = "Add a new Moodle MCP block";
$string["mcp:manage"] = "Manage course MCP integration settings";
$string["mcp:myaddinstance"] = "Add a Moodle MCP block to the dashboard";
$string["mcp:teach"] = "Use the teacher MCP tools for a course";
$string["mcp:use"] = "Use Moodle MCP in a course";
$string["mcp:write"] = "Use approved course MCP write tools";
$string["pluginname"] = "Moodle MCP for ChatGPT";
$string["privacy:metadata"] = "The Moodle MCP block only stores course-level block settings and does not itself store personal data. OAuth connections and audit logs are managed by local_mcp.";
$string["securitynote"] = "Access is checked by Moodle on every request, and is limited to courses that enable this block.";
$string["settingsheading"] = "Course integration settings";
$string["stepone"] = "Open ChatGPT and configure a custom MCP connection if your account supports it.";
$string["stepthree"] = "Authorize the connection with your own Moodle account.";
$string["steptwo"] = "Use this Moodle MCP server URL:";
$string["studentdescription"] = "Study the visible course content and check your own progress, deadlines and grades in ChatGPT.";
$string["studentheading"] = "Your AI study assistant";
$string["teacherdescription"] = "Use ChatGPT to consult your course and organize your teaching activities.";
$string["teacherheading"] = "Your AI course assistant";
$string["teachers"] = "Teachers only";
$string["teachersandstudents"] = "Teachers and students";
$string["teacherwrite"] = "Allow teacher WRITE tools";
$string["teacherwrite_help"] = "Enables the selected, course-scoped MCP WRITE tools for users with block/mcp:write. The ChatGPT user must still confirm supported changes before execution. Site administration tools are never available in educational connections.";
$string["writedisabled"] = "This course allows read-only access through ChatGPT.";
$string["writeenabled"] = "Course management tools are enabled for your role.";
