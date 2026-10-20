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
 * Safely read visible Moodle study resources.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace block_mcp\read\tool;

use context;
use context_module;
use local_mcp\security\authenticated_identity;

/** Read the visible textual content of selected learning resources. */
final class get_study_content extends base_tool {
    public function get_name(): string {
        return "block_mcp_get_study_content";
    }

    public function get_title(): string {
        return "Read study content";
    }

    public function get_description(): string {
        return "Read visible Page, Label and Book text from an accessible Moodle course.";
    }

    public function get_input_schema(): array {
        return $this->schema(["cmid" => ["type" => "integer", "minimum" => 1]], ["cmid"]);
    }

    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $cmid = (int)$arguments["cmid"];
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $modinfo = get_fast_modinfo($course, $identity->userid);
        $visiblecm = $modinfo->cms[$cmid] ?? null;
        if (!$visiblecm || !$visiblecm->uservisible) {
            throw new \local_mcp\exception\api_exception("permission_denied", 403);
        }
        $context = context_module::instance($cmid);
        $result = ["courseid" => (int)$course->id, "cmid" => $cmid,
            "type" => $cm->modname, "title" => $cm->name, "sections" => []];
        if ($cm->modname === "page") {
            if (!has_capability("mod/page:view", $context, $identity->userid)) {
                throw new \local_mcp\exception\api_exception("permission_denied", 403);
            }
            $page = $DB->get_record("page", ["id" => $cm->instance],
                "id,content,contentformat", MUST_EXIST);
            $result["sections"][] = ["title" => $cm->name,
                "text" => self::plaintext($page->content, (int)$page->contentformat, $context)];
        } else if ($cm->modname === "label") {
            $label = $DB->get_record("label", ["id" => $cm->instance],
                "id,intro,introformat", MUST_EXIST);
            $result["sections"][] = ["title" => $cm->name,
                "text" => self::plaintext($label->intro, (int)$label->introformat, $context)];
        } else if ($cm->modname === "book") {
            if (!has_capability("mod/book:read", $context, $identity->userid)) {
                throw new \local_mcp\exception\api_exception("permission_denied", 403);
            }
            $chapters = $DB->get_records("book_chapters", ["bookid" => $cm->instance, "hidden" => 0],
                "pagenum ASC", "id,title,content,contentformat");
            foreach ($chapters as $chapter) {
                $result["sections"][] = ["title" => $chapter->title,
                    "text" => self::plaintext($chapter->content, (int)$chapter->contentformat, $context)];
            }
        } else {
            throw new \local_mcp\exception\api_exception("unsupported_resource", 400);
        }
        return $result;
    }

    /** Moodle formatting rules are applied before returning text to the LLM. */
    private static function plaintext(string $html, int $format, context $context): string {
        $rendered = format_text($html, $format, ["context" => $context, "filter" => true]);
        return trim(html_entity_decode(strip_tags(str_replace(["</p>", "<br>", "<br/>", "<br />"],
            "\n", $rendered)), ENT_QUOTES | ENT_HTML5, "UTF-8"));
    }
}
