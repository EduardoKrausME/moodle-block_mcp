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
 * Moodle PHPUnit access policy tests.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mcp;

use block_mcp\security\policy;
use context_course;

/** Course policy test cases. */
final class policy_test extends \advanced_testcase {
    /** A student must not inherit a ChatGPT connection from a course without this block. */
    public function test_no_block_means_no_educational_access(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, "student");
        $this->assertFalse(policy::course_access((int)$student->id, (int)$course->id)["allowed"]);
        $this->assertFalse(policy::user_has_access((int)$student->id));
    }

    /** Per-instance audience and write toggles cannot grant student writing. */
    public function test_course_block_audience_and_write_settings(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, "student");
        $generator->enrol_user($teacher->id, $course->id, "editingteacher");
        $ctx = context_course::instance($course->id);
        $block = (object)[
            "blockname" => "mcp", "parentcontextid" => $ctx->id, "showinsubcontexts" => 0,
            "pagetypepattern" => "course-view-*", "subpagepattern" => null,
            "defaultregion" => "side-pre", "defaultweight" => 0, "visible" => 1,
            "configdata" => base64_encode(serialize((object)["audience" => "teachers", "teacherwrite" => 0])),
        ];
        $block->id = $DB->insert_record("block_instances", $block);
        $this->assertFalse(policy::course_access($student->id, $course->id)["allowed"]);
        $this->assertTrue(policy::course_access($teacher->id, $course->id)["allowed"]);
        $this->assertFalse(policy::course_access($teacher->id, $course->id)["write"]);
        $block->configdata = base64_encode(serialize((object)["audience" => "all", "teacherwrite" => 1]));
        $DB->update_record("block_instances", $block);
        $this->assertTrue(policy::course_access($student->id, $course->id)["allowed"]);
        $this->assertFalse(policy::course_access($student->id, $course->id)["write"]);
        $this->assertTrue(policy::course_access($teacher->id, $course->id)["write"]);
        $this->assertFalse(policy::tool_visible($student->id, "set_site_config", false));
        $this->assertFalse(policy::tool_visible($student->id, "grade_submission", true));
        $this->assertTrue(policy::tool_visible($student->id, "block_mcp_get_my_grades", false));
        $DB->set_field("block_instances", "visible", 0, ["id" => $block->id]);
        $this->assertFalse(policy::user_has_access($student->id));
    }

    /** Block permissions have to be defined to allow adding course instances. */
    public function test_addinstance_capability_is_defined(): void {
        $capabilities = [];
        require(__DIR__ . "/../db/access.php");
        $this->assertArrayHasKey("block/mcp:addinstance", $capabilities);
        $this->assertSame(CONTEXT_BLOCK, $capabilities["block/mcp:addinstance"]["contextlevel"]);
        $this->assertArrayHasKey("block/mcp:myaddinstance", $capabilities);
        $this->assertEmpty($capabilities["block/mcp:myaddinstance"]["archetypes"]);
    }

    /** An enrolment must not bypass the course block once the block is hidden. */
    public function test_hidden_course_block_disables_access(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, "student");
        $ctx = context_course::instance($course->id);
        $block = (object)[
            "blockname" => "mcp", "parentcontextid" => $ctx->id, "showinsubcontexts" => 0,
            "pagetypepattern" => "course-view-*", "subpagepattern" => null,
            "defaultregion" => "side-pre", "defaultweight" => 0, "visible" => 0,
            "configdata" => base64_encode(serialize((object)["audience" => "all", "teacherwrite" => 0])),
        ];
        $DB->insert_record("block_instances", $block);
        $this->assertFalse(policy::course_access($student->id, $course->id)["allowed"]);
    }

    /** Course write settings never grant administrative or student write tools. */
    public function test_student_cannot_use_teacher_tools(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, "student");
        $ctx = context_course::instance($course->id);
        $instance = (object)[
            "blockname" => "mcp", "parentcontextid" => $ctx->id, "showinsubcontexts" => 0,
            "pagetypepattern" => "course-view-*", "subpagepattern" => null,
            "defaultregion" => "side-pre", "defaultweight" => 0, "visible" => 1,
            "configdata" => base64_encode(serialize((object)["audience" => "all", "teacherwrite" => 1])),
        ];
        $DB->insert_record("block_instances", $instance);
        $this->assertFalse(policy::tool_visible($student->id, "grade_submission", true));
        $this->assertFalse(policy::tool_visible($student->id, "set_site_config", true));
        $this->expectException(\local_mcp\exception\api_exception::class);
        policy::authorize_tool($student->id, "grade_submission", true, $ctx, ["courseid" => $course->id]);
    }
}
