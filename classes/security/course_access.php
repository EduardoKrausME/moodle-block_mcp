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
 * Course and block access checks for educational connections.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mcp\security;

use context_course;
use core_plugin_manager;
use stdClass;

/** Resolve access from the user's course capabilities and visible block instances. */
final class course_access {
    /**
     * Resolve the effective access for a single course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return array
     */
    public static function resolve(int $userid, int $courseid): array {
        global $DB;

        $denied = ["allowed" => false, "teacher" => false, "write" => false];
        if ($userid <= 0 || $courseid <= SITEID) {
            return $denied;
        }
        $plugininfo = core_plugin_manager::instance()->get_plugin_info("block_mcp");
        if (!$plugininfo || $plugininfo->is_enabled() === false) {
            return $denied;
        }
        $course = $DB->get_record("course", ["id" => $courseid], "id,visible");
        if (!$course) {
            return $denied;
        }
        $ctx = context_course::instance($courseid);
        if (!self::can_access_course($userid, $ctx, $course)) {
            return $denied;
        }
        $instance = self::visible_block($ctx);
        if (!$instance) {
            return $denied;
        }
        return [
            "allowed" => true,
            "teacher" => true,
            "write" => self::teacher_write_enabled((string)$instance->configdata)
                && has_capability("block/mcp:write", $ctx, $userid),
        ];
    }

    /**
     * Check teaching permissions, enrolment or management access, and course visibility.
     *
     * @param int $userid User ID.
     * @param context_course $ctx Course context.
     * @param stdClass $course Course record.
     * @return bool
     */
    private static function can_access_course(int $userid, context_course $ctx, stdClass $course): bool {
        // Legacy audience=all and a student :use permission must not grant access.
        if (!has_capability("block/mcp:teach", $ctx, $userid) ||
            !has_capability("block/mcp:use", $ctx, $userid)) {
            return false;
        }
        // A course manager can have access without an active enrolment.
        if (!is_enrolled($ctx, $userid, "", true) &&
            !has_capability("moodle/course:view", $ctx, $userid)) {
            return false;
        }
        return $course->visible || has_capability("moodle/course:viewhiddencourses", $ctx, $userid);
    }

    /**
     * Find the first eligible visible course block.
     *
     * @param context_course $ctx Course context.
     * @return stdClass|null
     */
    private static function visible_block(context_course $ctx): ?stdClass {
        global $DB;

        $instances = $DB->get_records("block_instances", [
            "blockname" => "mcp", "parentcontextid" => $ctx->id,
        ]);
        foreach ($instances as $instance) {
            if (!self::is_course_block($instance)) {
                continue;
            }
            // An explicit hidden position disables access; absence uses default visibility.
            if ($DB->record_exists("block_positions", [
                "blockinstanceid" => $instance->id, "visible" => 0,
            ])) {
                continue;
            }
            return $instance;
        }
        return null;
    }

    /** Ignore block instances not configured for a course page. */
    private static function is_course_block(stdClass $instance): bool {
        $pattern = (string)$instance->pagetypepattern;
        return $pattern === "course-view" || strpos($pattern, "course-view-") === 0;
    }

    /**
     * Read the write toggle using Moodle's safe block configuration decoder.
     *
     * @param string $data Base64-encoded block configuration.
     * @return bool
     */
    private static function teacher_write_enabled(string $data): bool {
        if ($data === "") {
            return false;
        }
        $decoded = base64_decode($data, true);
        if ($decoded === false || $decoded === "") {
            return false;
        }
        // Moodle permits stdClass only. Invalid serialized data must fail closed.
        $value = @unserialize_object($decoded);
        if (!self::is_valid_config($decoded, $value)) {
            return false;
        }
        $toggle = $value->teacherwrite ?? null;
        return is_scalar($toggle) && !empty($toggle);
    }

    /**
     * Accept intact native object or legacy array configurations only.
     *
     * @param string $decoded Serialized configuration.
     * @param stdClass $value Decoded configuration.
     * @return bool
     */
    private static function is_valid_config(string $decoded, stdClass $value): bool {
        // Older PHP versions can silently accept trailing bytes after serialized data.
        return serialize($value) === $decoded || serialize((array)$value) === $decoded;
    }
}
