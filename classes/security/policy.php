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
 * Mandatory course access policy for educational connections.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace block_mcp\security;

use context;
use context_course;
use context_module;
use context_system;
use local_mcp\exception\api_exception;

/**
 * Per-course policy applied to both discovery and execution in local_mcp.
 * All unknown tools and non-course contexts are denied for educational OAuth.
 */
final class policy {
    /** @var string[] Course-bound tools safe for student connections. */
    private const STUDENT_READ = [
        "get_course_contents", "get_course_activities", "get_course_sections", "get_progress",
        "get_assignment", "get_assignments", "get_quiz", "get_quizzes",
        "block_mcp_get_study_content", "block_mcp_get_my_tasks", "block_mcp_get_my_grades",
    ];

    /** @var string[] Additional course-bound tools for teachers. */
    private const TEACHER_READ = [
        "get_course", "get_submissions", "get_grades", "get_course_participants",
    ];

    /** @var string[] Curated WRITE tools for the first release. */
    private const TEACHER_WRITE = [
        "create_section", "update_section", "assign_create_activity",
        "grade_submission", "delete_course_module",
    ];

    /**
     * Resolve a single enabled course block and its user's effective capabilities.
     *
     * @return array{allowed:bool,teacher:bool,write:bool}
     */
    public static function course_access(int $userid, int $courseid): array {
        global $DB;

        $denied = ["allowed" => false, "teacher" => false, "write" => false];
        if ($userid <= 0 || $courseid <= SITEID) {
            return $denied;
        }
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info("block_mcp");
        if (!$plugininfo || $plugininfo->is_enabled() === false) {
            return $denied;
        }
        $course = $DB->get_record("course", ["id" => $courseid], "id,visible");
        if (!$course) {
            return $denied;
        }
        $ctx = context_course::instance($courseid);
        if (!is_enrolled($ctx, $userid, "", true) ||
            !has_capability("block/mcp:use", $ctx, $userid) ||
            !has_capability("moodle/course:view", $ctx, $userid)) {
            return $denied;
        }
        if (!$course->visible && !has_capability("moodle/course:viewhiddencourses", $ctx, $userid)) {
            return $denied;
        }
        $isteacher = has_capability("block/mcp:teach", $ctx, $userid);
        $instances = $DB->get_records("block_instances", [
            "blockname" => "mcp", "parentcontextid" => $ctx->id, "visible" => 1,
        ]);
        foreach ($instances as $instance) {
            if (!self::is_course_block($instance)) {
                continue;
            }
            $config = self::read_config((string)$instance->configdata);
            if (!$isteacher && ($config["audience"] ?? "teachers") !== "all") {
                continue;
            }
            return [
                "allowed" => true,
                "teacher" => $isteacher,
                "write" => $isteacher && !empty($config["teacherwrite"])
                    && has_capability("block/mcp:write", $ctx, $userid),
            ];
        }
        return $denied;
    }

    /** Ignore block instances not configured for a course page. */
    private static function is_course_block(\stdClass $instance): bool {
        $pattern = (string)$instance->pagetypepattern;
        return $pattern === "course-view-*" || $pattern === "course-view"
            || str_starts_with($pattern, "course-view-");
    }

    /** Decode only scalar configuration, without instantiating arbitrary PHP classes. */
    private static function read_config(string $data): array {
        if ($data === "") {
            return [];
        }
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return [];
        }
        $value = @unserialize($decoded, ["allowed_classes" => false]);
        if (!is_object($value) && !is_array($value)) {
            return [];
        }
        $values = (array)$value;
        return [
            "audience" => ($values["audience"] ?? "teachers") === "all" ? "all" : "teachers",
            "teacherwrite" => !empty($values["teacherwrite"]),
        ];
    }

    /** Find eligible courses for OAuth without consulting a global role name. */
    private static function any_course(int $userid, bool $write): bool {
        foreach (enrol_get_users_courses($userid, true, ["id"]) as $course) {
            $access = self::course_access($userid, (int)$course->id);
            if ($access[$write ? "write" : "allowed"]) {
                return true;
            }
        }
        return false;
    }

    /** Whether the logged-in user can initiate an educational OAuth flow. */
    public static function user_has_access(int $userid): bool {
        return self::any_course($userid, false);
    }

    /** Whether any course can justify requesting an OAuth WRITE scope. */
    public static function user_has_write_access(int $userid): bool {
        return self::any_course($userid, true);
    }

    /** Server-side discovery filtering; tool execution is checked again independently. */
    public static function tool_visible(int $userid, string $tool, bool $write): bool {
        if ($write) {
            return in_array($tool, self::TEACHER_WRITE, true) && self::user_has_write_access($userid);
        }
        if (in_array($tool, self::STUDENT_READ, true)) {
            return self::user_has_access($userid);
        }
        return in_array($tool, self::TEACHER_READ, true) && self::any_teacher_course($userid);
    }

    /** Search only enrolments, then verify teacher capabilities in each course. */
    private static function any_teacher_course(int $userid): bool {
        foreach (enrol_get_users_courses($userid, true, ["id"]) as $course) {
            $access = self::course_access($userid, (int)$course->id);
            if ($access["allowed"] && $access["teacher"]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Deny unknown tools, cross-course requests, foreign student data, and hidden modules.
     * Called on EVERY MCP tools/call, independently of tools/list and the OAuth scope.
     */
    public static function authorize_tool(int $userid, string $tool, bool $write,
                                           context $context, array $args): void {
        $coursectx = $context->contextlevel === CONTEXT_COURSE ? $context
            : $context->get_course_context(false);
        if (!$coursectx || $coursectx->contextlevel !== CONTEXT_COURSE) {
            self::deny();
        }
        $access = self::course_access($userid, (int)$coursectx->instanceid);
        if (!$access["allowed"]) {
            self::deny();
        }
        if ($write) {
            if (!$access["write"] || !in_array($tool, self::TEACHER_WRITE, true)) {
                self::deny();
            }
        } else if (!in_array($tool, self::STUDENT_READ, true) &&
            (!$access["teacher"] || !in_array($tool, self::TEACHER_READ, true))) {
            self::deny();
        }
        if (!$access["teacher"] && $tool === "get_progress" &&
            (int)($args["userid"] ?? 0) !== $userid) {
            self::deny();
        }
        // Bind explicit course identifiers to the context returned by the tool.
        if (isset($args["courseid"]) && (int)$args["courseid"] !== (int)$coursectx->instanceid) {
            self::deny();
        }
        if ($context->contextlevel === CONTEXT_MODULE) {
            $modinfo = get_fast_modinfo((int)$coursectx->instanceid, $userid);
            $cm = $modinfo->cms[(int)$context->instanceid] ?? null;
            if (!$cm || !$cm->uservisible) {
                self::deny();
            }
        }
    }

    /** Throw the standard local_mcp authorization exception. */
    private static function deny(): void {
        throw new api_exception("permission_denied", 403);
    }
}
