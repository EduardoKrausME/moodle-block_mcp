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
use local_mcp\exception\api_exception;

/**
 * Per-course policy applied to both discovery and execution in local_mcp.
 * All unknown tools and non-course contexts are denied for educational OAuth.
 */
final class policy {
    /** @var string[] Course-bound READ tools for authorized teaching staff. */
    private const COURSE_READ = [
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
        return course_access::resolve($userid, $courseid);
    }

    /** Include courses managed by non-enrolled staff and recheck each block. */
    private static function any_course(int $userid, bool $write): bool {
        $courses = get_user_capability_course("block/mcp:teach", $userid);
        foreach ($courses ?: [] as $course) {
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
        if (in_array($tool, self::COURSE_READ, true)) {
            return self::user_has_access($userid);
        }
        return in_array($tool, self::TEACHER_READ, true) && self::user_has_access($userid);
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
        if (!$access["allowed"] || !$access["teacher"]) {
            self::deny();
        }
        self::authorize_scope($tool, $write, $access);
        self::authorize_context($userid, $context, (int)$coursectx->instanceid, $args);
    }

    /**
     * Check the operation scope and the tool allowlist.
     *
     * @param string $tool Tool name.
     * @param bool $write Whether the operation writes.
     * @param array $access Effective course access.
     */
    private static function authorize_scope(string $tool, bool $write, array $access): void {
        if ($write) {
            if (!$access["write"] || !in_array($tool, self::TEACHER_WRITE, true)) {
                self::deny();
            }
        } else if (!in_array($tool, self::COURSE_READ, true) &&
            !in_array($tool, self::TEACHER_READ, true)) {
            self::deny();
        }
    }

    /**
     * Bind explicit course identifiers and check module visibility for this user.
     *
     * @param int $userid User ID.
     * @param context $context Tool context.
     * @param int $courseid Authorized course ID.
     * @param array $args Tool arguments.
     */
    private static function authorize_context(int $userid, context $context, int $courseid, array $args): void {
        if (isset($args["courseid"]) && (int)$args["courseid"] !== $courseid) {
            self::deny();
        }
        if ($context->contextlevel === CONTEXT_MODULE) {
            $modinfo = get_fast_modinfo($courseid, $userid);
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
