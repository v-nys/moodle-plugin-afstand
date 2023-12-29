<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_distance
 * @category    string
 * @copyright   2022 Vincent Nys <vincent.nys@ap.be>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Insert a link to index.php on the site front page navigation menu.
 *
 * @param navigation_node $frontpage Node representing the front page in the navigation tree.
 */
function local_distance_extend_navigation_frontpage(navigation_node $frontpage) {
    $frontpage->add(
        get_string('pluginname', 'local_distance'),
        new moodle_url('/local/distance/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        null,
        new pix_icon('t/online', '')
    );
}

/**
 * Insert a link to index.php in the navigation sidebar.
 *
 * @param global_navigation $root Node representing the root of the navigation tree.
 */
function local_distance_extend_navigation(global_navigation $root) {
    $node = $root->add(
        get_string('pluginname', 'local_distance'),
        new moodle_url('/local/distance/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        null,
        new pix_icon('t/online', '')
    );
    $node->showinflatnavigation = true;
}

