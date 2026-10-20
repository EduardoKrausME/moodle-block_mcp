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
 * Course MCP connection block.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined("MOODLE_INTERNAL") || die;

/**
 * Course block that advertises OAuth-based Moodle MCP integration.
 */
class block_mcp extends block_base {
    /** @var stdClass|null */
    public $content = null;

    /** Initialize the title. */
    public function init(): void {
        $this->title = get_string("pluginname", "block_mcp");
    }

    /** This block only makes sense on course pages. */
    public function applicable_formats(): array {
        return ["all" => false, "course-view" => true];
    }

    /** Avoid multiple policy configurations in one course. */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /** Give editing teachers a per-course configuration form. */
    public function instance_allow_config(): bool {
        $context = $this->page->context;
        return $context && has_capability("block/mcp:manage", $context);
    }

    /** Only teachers with the course management capability may edit the block. */
    public function user_can_edit(): bool {
        return parent::user_can_edit()
            && has_capability("block/mcp:manage", $this->page->context);
    }

    /** Render all visible content via Mustache. */
    public function get_content() {
        global $CFG, $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = "";
        $this->content->footer = "";
        $courseid = (int)($this->page->course->id ?? 0);
        if ($courseid <= SITEID || !isloggedin() || isguestuser()) {
            return $this->content;
        }
        $access = \block_mcp\security\policy::course_access((int)$USER->id, $courseid);
        if (!$access["allowed"]) {
            return $this->content;
        }
        $context = \context_course::instance($courseid);
        $data = [
            "courseid" => $courseid,
            "isteacher" => $access["teacher"],
            "canwrite" => $access["write"],
            "coursename" => format_string($this->page->course->fullname, true, ["context" => $context]),
            "endpoint" => rtrim($CFG->wwwroot, "/") . "/local/mcp/server.php",
            "chatgpturl" => "https://chatgpt.com/",
            "canmanage" => has_capability("block/mcp:manage", $context, $USER->id),
        ];
        $this->content->text = $OUTPUT->render_from_template("block_mcp/content", $data);
        return $this->content;
    }

    /** Hide the box rather than showing an empty integration. */
    public function is_empty() {
        $content = $this->get_content();
        return empty($content->text);
    }
}
