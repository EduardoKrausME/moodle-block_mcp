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
 * Visible coursework and deadlines for the current user.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace block_mcp\read\tool;

use local_mcp\security\authenticated_identity;

/** Assignment and quiz deadlines from modules visible to the connected user. */
final class get_my_tasks extends base_tool {
    public function get_name(): string {
        return "block_mcp_get_my_tasks";
    }

    public function get_title(): string {
        return "Get my upcoming coursework";
    }

    public function get_description(): string {
        return "List my visible assignments and quizzes with deadlines and submission state.";
    }

    public function get_input_schema(): array {
        return $this->schema(["courseid" => ["type" => "integer", "minimum" => 2]], ["courseid"]);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $courseid = (int)$arguments["courseid"];
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course, $identity->userid);
        $tasks = [];
        foreach ($modinfo->cms as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            if ($cm->modname === "assign" && has_capability("mod/assign:view", $cm->context, $identity->userid)) {
                $assignment = $DB->get_record("assign", ["id" => $cm->instance], "id,duedate,cutoffdate");
                if (!$assignment) {
                    continue;
                }
                $submission = $DB->get_record("assign_submission", [
                    "assignment" => $assignment->id, "userid" => $identity->userid, "latest" => 1,
                ], "id,status,timemodified");
                $tasks[] = ["cmid" => (int)$cm->id, "type" => "assign", "name" => $cm->name,
                    "due" => (int)$assignment->duedate, "cutoff" => (int)$assignment->cutoffdate,
                    "state" => $submission ? $submission->status : "not_submitted"];
            } else if ($cm->modname === "quiz" && has_capability("mod/quiz:view", $cm->context, $identity->userid)) {
                $quiz = $DB->get_record("quiz", ["id" => $cm->instance], "id,timeopen,timeclose");
                if ($quiz) {
                    $tasks[] = ["cmid" => (int)$cm->id, "type" => "quiz", "name" => $cm->name,
                        "opens" => (int)$quiz->timeopen, "due" => (int)$quiz->timeclose];
                }
            }
        }
        return ["courseid" => $courseid, "tasks" => $tasks];
    }
}
