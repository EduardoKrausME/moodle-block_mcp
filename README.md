# block_mcp — Moodle MCP for ChatGPT

A Moodle 4.5+/5.x course block that permits teachers, or teachers and students, to connect their own ChatGPT account to Moodle. Teacher WRITE tools are course-scoped and disabled by default.

## Requirements

- Install `local_mcp` (version 2026101900 or newer).
- **Apply the accompanying local_mcp integration patch** before enabling student or teacher OAuth. The upstream authorization endpoint requires `moodle/site:config` and would otherwise reject them. The patch is maintained separately and is not part of this repository commit.
- The ChatGPT workspace/account must support custom MCP server connections.

## Installation and operation

Install this repository under `blocks/mcp`, upgrade Moodle, then add the block to a course and choose the audience. Authorize the MCP endpoint `/local/mcp/server.php` through ChatGPT using each user's own Moodle account. The block does not issue shared tokens.

## Permission model

Course-scoped checks require an enabled, visible course block, active enrolment, Moodle capabilities and the individual course settings. Every MCP operation is checked again at execution time by the required local_mcp integration patch. Unknown tools and system, category and user-level contexts are denied for educational users. READ and WRITE tool lists are explicitly allowlisted and administrative tools are unavailable to educational users.

Available tools include `block_mcp_get_study_content`, `block_mcp_get_my_tasks`, and `block_mcp_get_my_grades`. The original local_mcp READ/WRITE registries remain responsible for tool execution and confirmation previews.

## Validation

Use the repository validator at `EduardoKrausME/moodle-plugin-validate` and test in Moodle 4.5 and 5.x with PHPUnit. Local syntax and isolated policy checks have been performed, but real Moodle integration/CI testing is still required.
