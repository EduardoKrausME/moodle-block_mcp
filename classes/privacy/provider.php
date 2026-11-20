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
 * Privacy API provider.
 *
 * @package   block_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace block_mcp\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/** The block itself stores no personal information. */
final class provider implements metadata_provider {
    /** Declare the learner data transmitted through the configured MCP service. */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('mcpclient', [
            'userid' => 'privacy:metadata:mcpclient:userid',
            'grades' => 'privacy:metadata:mcpclient:grades',
            'coursework' => 'privacy:metadata:mcpclient:coursework',
            'participants' => 'privacy:metadata:mcpclient:participants',
        ], 'privacy:metadata:mcpclient');
        return $collection;
    }
}
