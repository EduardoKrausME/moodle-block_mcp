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
 * Grades visible to the authenticated student.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace block_mcp\read\tool;

use context_course;
use local_mcp\security\authenticated_identity;

/** Only show the connected user's own non-hidden grades. */
final class get_my_grades extends base_tool {
    public function get_name(): string {
        return "block_mcp_get_my_grades";
    }

    public function get_title(): string {
        return "Get my visible grades";
    }

    public function get_description(): string {
        return "Retrieve only my non-hidden gradebook items in an accessible Moodle course.";
    }

    public function get_input_schema(): array {
        return $this->schema(["courseid" => ["type" => "integer", "minimum" => 2]], ["courseid"]);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->libdir . "/gradelib.php");
        require_once($CFG->libdir . "/grade/grade_item.php");
        require_once($CFG->libdir . "/grade/grade_grade.php");
        $courseid = (int)$arguments["courseid"];
        $context = context_course::instance($courseid);
        $course = get_course($courseid);
        if (!$course->showgrades &&
            !has_capability("moodle/grade:viewall", $context, $identity->userid)) {
            throw new \local_mcp\exception\api_exception("permission_denied", 403);
        }
        if (!has_capability("moodle/grade:view", $context, $identity->userid)
            && !has_capability("moodle/grade:viewall", $context, $identity->userid)) {
            throw new \local_mcp\exception\api_exception("permission_denied", 403);
        }
        $data = grade_get_grades($courseid, "", "", 0, $identity->userid);
        $out = [];
        foreach ($data->items ?? [] as $item) {
            $gradeitem = \grade_item::fetch(["id" => (int)$item->id]);
            if (!$gradeitem || $gradeitem->is_hidden()) {
                continue;
            }
            $grade = \grade_grade::fetch(["itemid" => $gradeitem->id, "userid" => $identity->userid]);
            if ($grade && $grade->is_hidden()) {
                continue;
            }
            $entry = $item->grades[$identity->userid] ?? null;
            $out[] = ["item" => $item->name, "formatted" => $entry?->str_grade,
                "minimum" => (float)$item->grademin, "maximum" => (float)$item->grademax];
        }
        return ["courseid" => $courseid, "userid" => $identity->userid, "grades" => $out];
    }
}
