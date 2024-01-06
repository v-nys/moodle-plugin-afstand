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
 * Serve the requested file for the local_distance plugin.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function local_distance_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options
): bool {
    global $DB;

    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel != CONTEXT_LOCAL) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'navigation') {
        return false;
    }

    // Make sure the user is logged in.
    require_login($course, true);

    // Would normally check the relevant capabilities here.
    // Skipping this for prototype.

    // The args is an array containing [itemid, path].
    // Fetch the itemid from the path.
    $itemid = array_shift($args);

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (empty($args)) {
        // $args is empty => the path is '/'.
        $filepath = '/';
    } else {
        // $args contains the remaining elements of the filepath.
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_distance', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        // The file does not exist.
        return false;
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    send_stored_file($file, DAY_SECS, 0, $forcedownload, $options);
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

