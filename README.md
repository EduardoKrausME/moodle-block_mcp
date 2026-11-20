# block_mcp - Moodle MCP for ChatGPT

A Moodle 4.5+/5.x course block that permits teachers and course managers (including non-enrolled managers) to connect their own ChatGPT account to Moodle. WRITE tools are course-scoped and disabled by default.

## Requirements

- Install `local_mcp` (version 2026112000 or newer).
- **Apply the accompanying local_mcp integration patch** before enabling teacher OAuth. The upstream authorization endpoint requires `moodle/site:config` and would otherwise reject non-administrative teachers. The patch is maintained separately and is not part of this repository commit.
- The ChatGPT workspace/account must support custom MCP server connections.

## Installation and operation

Install this repository under `blocks/mcp`, upgrade Moodle, then add the block to a course. The block is only rendered for users who have the `block/mcp:teach` and `block/mcp:use` capabilities. Authorize the MCP endpoint `/local/mcp/server.php` through ChatGPT using each user's own Moodle account. The block does not issue shared tokens.

## Permission model

Course-scoped checks require an enabled, visible course block, teaching capabilities and the individual course settings. Teachers must be enrolled or have `moodle/course:view`, so authorized managers can access a course without being enrolled. Students cannot view the block, discover or execute its educational tools, or connect through this block, even when an older block instance contains `audience=all` or a student still has `block/mcp:use`. Every MCP operation is checked again at execution time by the local_mcp access policy extension. Unknown tools and system, category and user-level contexts are denied for educational users. READ and WRITE tool lists are explicitly allowlisted and administrative tools are unavailable to educational users.

Available tools include `block_mcp_get_study_content`, `block_mcp_get_my_tasks`, and `block_mcp_get_my_grades`. The original local_mcp READ/WRITE registries remain responsible for tool execution and confirmation previews.

## Validation

Use the repository validator at `EduardoKrausME/moodle-plugin-validate` and test in Moodle 4.5 and 5.x with PHPUnit. Local syntax and isolated policy checks have been performed, but real Moodle integration/CI testing is still required.
