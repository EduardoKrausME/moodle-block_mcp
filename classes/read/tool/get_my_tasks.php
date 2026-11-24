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

use assign;
use cm_info;
use local_mcp\security\authenticated_identity;
use stdClass;

/** Assignment and quiz deadlines from modules visible to the connected user. */
final class get_my_tasks extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return "block_mcp_get_my_tasks";
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return "Get my upcoming coursework";
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return "List my visible assignments and quizzes with deadlines and submission state.";
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->schema(["courseid" => ["type" => "integer", "minimum" => 2]], ["courseid"]);
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . "/mod/assign/locallib.php");
        require_once($CFG->dirroot . "/mod/quiz/lib.php");
        $courseid = (int)$arguments["courseid"];
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course, $identity->userid);
        $tasks = [];
        foreach ($modinfo->cms as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $task = $this->get_task($cm, $course, $identity->userid);
            if ($task !== null) {
                $tasks[] = $task;
            }
        }
        return ["courseid" => $courseid, "tasks" => $tasks];
    }

    /**
     * Return a task only for an activity the connected user can view.
     *
     * @param cm_info $cm Course module.
     * @param stdClass $course Course record.
     * @param int $userid Connected user ID.
     * @return array|null
     */
    private function get_task(cm_info $cm, stdClass $course, int $userid): ?array {
        if ($cm->modname === "assign" && has_capability("mod/assign:view", $cm->context, $userid)) {
            return $this->get_assignment_task($cm, $course, $userid);
        }
        if ($cm->modname === "quiz" && has_capability("mod/quiz:view", $cm->context, $userid)) {
            return $this->get_quiz_task($cm, $userid);
        }
        return null;
    }

    /**
     * Resolve assignment deadlines, overrides, extensions and submission state.
     *
     * @param cm_info $cm Course module.
     * @param stdClass $course Course record.
     * @param int $userid Connected user ID.
     * @return array|null
     */
    private function get_assignment_task(cm_info $cm, stdClass $course, int $userid): ?array {
        global $DB;

        $assignment = $DB->get_record("assign", ["id" => $cm->instance], "id,duedate,cutoffdate");
        if (!$assignment) {
            return null;
        }
        $submission = $DB->get_record("assign_submission", [
            "assignment" => $assignment->id, "userid" => $userid, "latest" => 1,
        ], "id,status,timemodified");
        $module = get_coursemodule_from_id("assign", (int)$cm->id, (int)$course->id, false, MUST_EXIST);
        $instance = new assign($cm->context, $module, $course);
        $instance->update_effective_access($userid);
        $effective = $instance->get_instance($userid);
        $flags = $DB->get_record("assign_user_flags", [
            "assignment" => $assignment->id, "userid" => $userid,
        ], "extensionduedate");
        $extension = (int)($flags->extensionduedate ?? 0);
        $due = max($extension, (int)$effective->duedate);
        return ["cmid" => (int)$cm->id, "type" => "assign", "name" => $cm->name,
            "due" => $due, "cutoff" => (int)$effective->cutoffdate,
            "state" => $submission ? $submission->status : "not_submitted"];
    }

    /**
     * Resolve quiz deadlines with the connected user's effective overrides.
     *
     * @param cm_info $cm Course module.
     * @param int $userid Connected user ID.
     * @return array|null
     */
    private function get_quiz_task(cm_info $cm, int $userid): ?array {
        global $DB;

        $quiz = $DB->get_record("quiz", ["id" => $cm->instance]);
        if (!$quiz) {
            return null;
        }
        $quiz = quiz_update_effective_access($quiz, $userid);
        return ["cmid" => (int)$cm->id, "type" => "quiz", "name" => $cm->name,
            "opens" => (int)$quiz->timeopen, "due" => (int)$quiz->timeclose];
    }
}
