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

/** Test plugin for development.
 * @package     local_distance
 * @copyright   2022-2024 Vincent Nys <vincent.nys@ap.be>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php'); // Meestal nodig.
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/filelib.php');
require_login();
$FRANKENSTYLE_PLUGIN_NAME = 'local_distance';

$context = context_system::instance(); // TODO: this doesn't seem to do anything, though...
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/distance/index.php'));
$PAGE->set_pagelayout('standard'); // Zodat we blocks hebben.
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading(get_string('pluginname', 'local_distance'));

// first section to be created will just be number 1
$next_created_section_number = 1;

function create_course_topic($DB, $course, $key, $topic_section_produced_metadata, $topic_section_consumed_metadata, $topic_section_location)
{

    echo "creating course topic:";
    var_dump($key); // for debugging, in case some sections are created and process ends midway

    global $next_created_section_number;
    global $FRANKENSTYLE_PLUGIN_NAME;

    $preceding_course_module_id_in_section = -1; // first course module will be numbered 0
    $section_number = $next_created_section_number;
    $next_created_section_number += 1;
    $topic_title = $topic_section_consumed_metadata["title"];

    $record = new stdClass;
    $record->course = intval($course->id);
    $record->section = $section_number;
    $record->name = $topic_title;
    $record->summary = ""; // could add icon and short description here...
    $record->summaryformat = 1;
    $record->sequence = "";
    $record->visible = 1;
    $topic_section_produced_metadata[$key] = [
        "assignments" => []
    ];
    echo "inserting into course_sections";
    $topic_section_produced_metadata[$key]['moodle_section_id'] = $DB->insert_record('course_sections', $record);

    // CREATE CONTENT PAGE
    $content_location = $topic_section_location . "/" . "contents.html";
    $contents = file_get_contents($content_location);
    list($module, $context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'page', $section_number);
    $title = $topic_section_consumed_metadata["title"];
    $data->name = "Uitleg \"$title\"";
    $data->course = $course;
    $data->intro = text_to_html("Uitleg", false, false, true);
    $data->content = $contents; // misschien nodig text_to_html te gebruiken...
    $data->printintro = true;
    $data->showintro = true;
    $data->introformat = FORMAT_HTML;
    $data->alwaysshowdescription = false; // to do with temporal aspect
    $data->visibleoncoursepage = true;
    $data->completionview = COMPLETION_VIEW_REQUIRED;
    $data->completion = COMPLETION_TRACKING_AUTOMATIC;
    $data->completionexpected = 0;
    $data->completionunlocked = "1"; // see add_moduleinfo definition
    echo "adding moduleinfo";
    add_moduleinfo($data, $course);

    $preceding_course_module_id_in_section = $data->coursemodule;
    list($module, $moduleinfo_context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'assign', $section_number);
    echo html_writer::start_tag('p') . "Beginning assignment creation." . html_writer::end_tag('p');
    var_dump($topic_section_consumed_metadata);
    if (array_key_exists("assignments", $topic_section_consumed_metadata)) {
	    echo "there are assignments";
        foreach ($topic_section_consumed_metadata["assignments"] as $assignment_counter_for_topic => $assignment) {
            $description = $assignment['title'];
            $assignment_folder_location = $topic_section_location . "/assignments/" . $assignment["id"];
            // for now, just set this via $data->intro or something
            $assignment_description_location = $assignment_folder_location . "/contents.html";
            if (array_key_exists("attachments", $assignment)) {
                $attached_files = [];
                foreach ($assignment['attachments'] as $attachment_filename) {
                    echo html_writer::start_tag('p') . "Adding attachment $attachment_filename." . html_writer::end_tag('p');
                    $attachment_location = $assignment_folder_location . "/attachments/" . $attachment_filename;
                    // FIXME: missing contextid?
                    $attachment_filerecord = [
                        'filearea'  => "draft", // same as for manually created ones...
                        'itemid'    => file_get_unused_draft_itemid(),
                        'filepath'  => "/", // affects how the attachment is displayed (in folder structure or flat)
                        'filename'  => $attachment_filename,
                        'contextid' => $moduleinfo_context->id,
                        'component' => "user",
                        'license' => 'unknown',
                        'author' => 'script',
                        'sortorder' => 0,
                        'source' => null
                    ];
                    $attachment_storedfile = get_file_storage()->create_file_from_pathname($attachment_filerecord, $attachment_location);
                    echo html_writer::start_tag('p') . "Stored attachment $attachment_filename." . html_writer::end_tag('p');
                    array_push($attached_files, $attachment_storedfile->get_itemid());
                }
            }
            $human_counter = $assignment_counter_for_topic + 1;
            $data->name = "Opdracht $human_counter $topic_title: $description";
            $data->course = $course;
            // NOTE: setting $data->intro here does not work
            // field is ignored at creation for some reason, updating it does work
            $intro = file_get_contents($assignment_description_location);
            $data->introformat = FORMAT_HTML;
            $data->alwaysshowdescription = false;
            $data->submissiondrafts = 0;
            $data->sendnotifications = 0;
            $data->sendlatenotifications = 0;
            $data->sendstudentnotifications = 0;
            $data->requiresubmissionstatement = 0;
            $data->allowsubmissionsfromdate = 0;
            $data->grade = 0;
            $data->completionsubmit = 1;
            $data->duedate = 0;
            $data->markingworkflow = 0;
            $data->markingallocation = 0;
            $data->teamsubmission = 0;
            $data->requireallteammemberssubmit = 0;
            $data->gradingduedate = 0;
            $data->cutoffdate = 0;
            $data->hidegrader = 0;
            $data->blindmarking = 0;
            $data->teamsubmissiongroupingid = 0;
            $data->maxattempts = -1; // unlimited
            $data->attemptreopenmethod = 'untilpass';
            $data->preventsubmissionnotingroup = 0;
            $data->requiresubmissionstatement = 0;
            $data->assignsubmission_file_enabled = "1";
            $data->assignsubmission_comments_enabled = 1;
            $data->assignsubmission_file_maxfiles = "1";
            $data->assignsubmission_file_maxsizebytes = "5242880";
            $data->assignsubmission_file_filetypes = ".zip";
            $data->assignfeedback_comments_enabled = "1";
            $data->assignfeedback_comments_commentinline = "0";
            $data->submissiondrafts = "0";
            $data->completionunlocked = 1;
            $data->completion = "2";
            $data->completionview = "1";
            $data->completionexpected = 0;
            $data->completiongradeitemnumber = NULL;
            $data->introattachments = $attached_files;
            $availability = array(
                "op" => "&",
                "c" => [
                    array(
                        "type" => "completion",
                        "cm" => "$preceding_course_module_id_in_section",
                        "e" => 1
                    )
                ],
                "showc" => [true]
            );
            $data->availability = json_encode($availability);
            echo html_writer::start_tag('p') . "Adding moduleinfo for $topic_title." . html_writer::end_tag('p');
            $result = add_moduleinfo($data, $course);
            // update seems to be needed because add_moduleinfo does not handle the intro field
            // feels like this may have been fixed in more recent Moodle versions...?
            $data->id = $result->instance;
            $existing_assignment = $DB->get_record('assign', ['id' => $result->instance]);
            $existing_assignment->intro = $intro;
            $result = $DB->update_record('assign', $existing_assignment);
            array_push($topic_section_produced_metadata[$key]['assignments'], $data->coursemodule);
            $preceding_course_module_id_in_section = $data->coursemodule;
        }
    }

    // add final assignment for manual completion
    list($module, $context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'assign', $section_number);
    $title = $topic_section_consumed_metadata["title"];
    $data->name = "Manueel aanduiden: \"$title\" is duidelijk";
    $data->course = $course;
    // dit verschijnt niet, heeft dit te maken met ontbreken van veld rond regel 430 in mod/assign/externallib.php?
    // zie mss ook mod/assign/mod_form.php...
    $data->intro = text_to_html("Markeer deze activiteit handmatig als voltooid als $key duidelijk is.", false, false, true);
    $data->content = "Who knows?";
    $data->text = "This might appear...";
    $data->printintro = true;
    $data->showintro = true;
    $data->introformat = FORMAT_HTML;
    $data->alwaysshowdescription = false; // to do with temporal aspect
    $data->visibleoncoursepage = true;
    $data->nosubmissions = 1;
    $data->submissiondrafts = 0;
    $data->sendnotifications = 0;
    $data->sendlatenotifications = 0;
    $data->sendstudentnotifications = 0;
    $data->requiresubmissionstatement = 0;
    $data->allowsubmissionsfromdate = 0;
    $data->grade = 0;
    $data->duedate = 0;
    $data->markingworkflow = 0;
    $data->markingallocation = 0;
    $data->teamsubmission = 0;
    $data->requireallteammemberssubmit = 0;
    $data->gradingduedate = 0;
    $data->cutoffdate = 0;
    $data->hidegrader = 0;
    $data->blindmarking = 0;
    $data->revealidentities = 0;
    $data->teamsubmissiongroupingid = 0;
    $data->maxattempts = -1; // unlimited
    $data->attemptreopenmethod = 'none';
    $data->preventsubmissionnotingroup = 0;
    $data->requiresubmissionstatement = 0;
    $data->completionview = COMPLETION_VIEW_NOT_REQUIRED; // viewing does not count towards completion
    // note: only possible to track completion for enrolled (even admin) users!
    $data->completion = COMPLETION_TRACKING_MANUAL;
    $data->completionexpected = 0;
    $data->completionunlocked = "1"; // see add_moduleinfo definition
    $data->completiongradeitemnumber = NULL;
    // don't make it possible to manually complete something if there are preceding assignments,...
    $availability = array(
        "op" => "&",
        "c" => [
            array(
                "type" => "completion",
                "cm" => "$preceding_course_module_id_in_section",
                "e" => 1
            )
        ],
        "showc" => [true]
    );
    $data->availability = json_encode($availability);
    add_moduleinfo($data, $course);
    $preceding_course_module_id_in_section = $data->coursemodule;
    $topic_section_produced_metadata[$key]['manual_completion_assignment_id'] = $data->coursemodule;
    return $topic_section_produced_metadata;
}

echo $OUTPUT->header();
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // may want to add a warning before course is overwritten
        // but just adding an onsubmit here does not seem to work
        // a stubbed confirm shows up, but choosing 'cancel' does not cancel submission
        echo html_writer::start_tag('form', array('enctype' => 'multipart/form-data', 'action' => '#', 'method' => 'POST'));

        $courses = $DB->get_records('course', []);

        echo html_writer::start_tag('div');
        echo html_writer::start_tag('input', array('type' => 'radio', 'id' => 'replace', 'name' => 'update_mode', 'value' => 'replace'));
        echo html_writer::end_tag('input');
        echo html_writer::start_tag('label', array('for' => 'replace'));
        echo "Replace (delete existing content)";
        echo html_writer::end_tag('label');

        echo html_writer::start_tag('input', array('type' => 'radio', 'id' => 'update', 'name' => 'update_mode', 'value' => 'update'));
        echo html_writer::end_tag('input');
        echo html_writer::start_tag('label', array('for' => 'update'));
        echo html_writer::start_tag('span', array('style' => 'color: red;'));
        echo "(HIGHLY EXPERIMENTAL)";
        echo html_writer::end_tag('span');
        echo "Update (retain existing content)";
        echo html_writer::end_tag('label');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div');
        echo html_writer::start_tag('label', array('for' => 'course-select-dropdown'));
        echo "Use existing course:";
        echo html_writer::end_tag('label');
        echo html_writer::start_tag('select', array('id' => 'course-select-dropdown', 'name' => 'course'));
        foreach ($courses as $course) {
            echo html_writer::start_tag('option', array('value' => $course->id));
            echo $course->fullname;
            echo html_writer::end_tag('option');
        }
        echo html_writer::end_tag('select');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div');
        echo html_writer::start_tag('label', array('for' => 'archive-upload-button'));
        echo "Archive:";
        echo html_writer::end_tag('label');
        echo html_writer::start_tag('input', array('type' => 'file', 'id' => 'archive-upload-button', 'name' => 'archive'));
        echo html_writer::end_tag('input');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div');
        echo html_writer::start_tag('p');
        echo "If the goal is to add new sections to an existing course, set to the existing maximum + 1. Otherwise, set to 1.";
        echo html_writer::end_tag('p');
        echo html_writer::start_tag('label', array('for' => 'initial-section-id'));
        echo "Initial section ID:";
        echo html_writer::end_tag('label');
        echo html_writer::start_tag('input', array('type' => 'number', 'id' => 'initial-section-id', 'name' => 'initial-section-id'));
        echo html_writer::end_tag('input');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('input', array('type' => 'submit', 'value' => 'Recreate course'));
        echo html_writer::end_tag('form');
        break;
    case 'POST':
        $mode = $_POST['update_mode'];
        $next_created_section_number = intval($_POST['initial-section-id']);
        if ($mode === 'replace') {
            $course = $DB->get_record('course', ['id' => intval($_POST['course'])]);
            $DB->delete_records('course_modules', array('course' => $course->id));
            $DB->delete_records('course_sections', array('course' => $course->id));
            if ($_FILES['archive']['type'] === "application/zip" || $_FILES['archive']['type'] === "application/x-zip-compressed") {
                $zip = new ZipArchive;
                $open_res = $zip->open($_FILES['archive']['tmp_name']);
                if ($open_res === TRUE) {
                    $location = '/tmp/' . basename($_FILES['archive']['name']);
                    if (substr($location, -4) === ".zip") {
                        $location = substr($location, 0, strlen($location) - 4);
                    }
                    $zip->extractTo($location);
                    $zip->close();
                    // create a section for the course "map"
                    $record = new stdClass;
                    $record->course = intval($course->id);
                    $record->section = $next_created_section_number;
                    $next_created_section_number += 1;
                    $record->name = "overview";
                    $record->summary = file_get_contents($location . "/course_structure.svg");
                    $record->summaryformat = 1;
                    $record->sequence = "";
                    $record->visible = 1;
                    $maps_section_id = $DB->insert_record('course_sections', $record);
                    $absolute_svg_path = $location . "/course_structure.svg";
                    $unlocking_contents = file_get_contents($location . "/unlocking_conditions.json");
                    $directory_contents = array_diff(scandir($location), array('..', '.'));
                    $sql = "SELECT id FROM {clusters} WHERE courseid = ?";
                    $deleted_clusters = $DB->get_records_sql($sql, [$record->course]);
                    foreach ($deleted_clusters as $deleted_cluster) {
                        $DB->delete_records("nodes", array("clusters_id" => $deleted_cluster->id));
                    }
                    $DB->delete_records("clusters", array("courseid" => $record->course));
                    $cluster_ids = array();
                    foreach ($directory_contents as $file) {
                        if (is_dir($location . "/" . $file)) {
                            $cluster_record = new StdClass;
                            $cluster_record->name = $file;
                            $cluster_record->courseid = $record->course;
                            $cluster_record->yaml = file_get_contents($location . "/" . $file . "/contents.lc.yaml");
                            $cluster_ids[$file] = $DB->insert_record("clusters", $cluster_record);
                        }
                    }
                    $unlocking_conditions = json_decode($unlocking_contents, true);
                    // intended keys: moodle_section_id, manual_completion_assignment_id, assignments
                    $topic_section_produced_metadata = array();
                    foreach ($unlocking_conditions as $key => $value) {
                        $id_components = explode("__", $key);
                        $cluster_name = $id_components[0];
                        $unnamespaced_id = $id_components[1];
                        $cluster_folder = $location . "/" . $cluster_name;
                        $raw_cluster_data = file_get_contents($cluster_folder . "/contents.lc.json");
                        $cluster_metadata = json_decode($raw_cluster_data, true);
                        $topic_section_consumed_metadata = null;
                        foreach ($cluster_metadata["nodes"] as $node_metadata) {
                            if ($node_metadata["id"] === $unnamespaced_id) {
                                $topic_section_consumed_metadata = $node_metadata;
                            }
                        }
                        // TODO: do I really need to mutate *and* return?
                        $topic_section_produced_metadata = create_course_topic($DB, $course, $key, $topic_section_produced_metadata, $topic_section_consumed_metadata, $cluster_folder . "/" . $unnamespaced_id);

                        $node_record = new StdClass;
                        $node_record->slug = $unnamespaced_id;
                        $node_record->course_sections_id = $topic_section_produced_metadata[$key]['moodle_section_id'];
                        $node_record->clusters_id = $cluster_ids[$cluster_name];
                        $node_record->manual_completion_assignment_id = $topic_section_produced_metadata[$key]['manual_completion_assignment_id'];
                        $node_id = $DB->insert_record("nodes", $node_record);
                        $topic_section_produced_metadata[$key]['node_id'] = $node_id;
                    }
                    $namespaced_id_to_completion_id = function ($namespaced_id) use ($topic_section_produced_metadata) {
                        return $topic_section_produced_metadata[$namespaced_id]['manual_completion_assignment_id'];
                    };
                    $namespaced_id_to_node_id = function ($namespaced_id) use ($topic_section_produced_metadata) {
                        return $topic_section_produced_metadata[$namespaced_id]['node_id'];
                    };
                    $completion_id_to_condition = function ($cm_id) {
                        return array(
                            "type" => "completion",
                            "cm" => $cm_id,
                            "e" => 1
                        );
                    };
                    foreach ($unlocking_conditions as $key => $completion_criteria) {
                        $node_id = $topic_section_produced_metadata[$key]['node_id'];
                        if ($completion_criteria) {
                            $course_section_id = $topic_section_produced_metadata[$key]['moodle_section_id'];
                            $course_section_record = $DB->get_record('course_sections', ['id' => $course_section_id]);
                            $all_type_dependency_completion_ids = array_map($namespaced_id_to_completion_id, $completion_criteria['all_of']);
                            $one_type_dependency_completion_ids = array_map($namespaced_id_to_completion_id, $completion_criteria['one_of']);
                            $all_type_dependency_node_ids = array_map($namespaced_id_to_node_id, $completion_criteria['all_of']);
                            $one_type_dependency_node_ids = array_map($namespaced_id_to_node_id, $completion_criteria['one_of']);

                            foreach ($all_type_dependency_node_ids as $dependency_id) {
                                $record = new StdClass();
                                $record->edge_type = "all";
                                $record->dependent = $node_id;
                                $record->dependency = $dependency_id;
                                $DB->insert_record('node_prerequisites', $record);
                            }

                            foreach ($one_type_dependency_node_ids as $dependency_id) {
                                $record = new StdClass();
                                $record->edge_type = "any";
                                $record->dependent = $node_id;
                                $record->dependency = $dependency_id;
                                $DB->insert_record('node_prerequisites', $record);
                            }


                            $all_type_conditions = array_map($completion_id_to_condition, $all_type_dependency_completion_ids);
                            $one_type_conditions = array_map($completion_id_to_condition, $one_type_dependency_completion_ids);


                            // Moodle does not apply logical meaning (empty conjunction = true / empty disjunction = false)
                            // otherwise, conjunction && disjunction would be sufficient
                            $conjunction = array(
                                "op" => "&",
                                "c" => $all_type_conditions,
                                "show" => true
                            );
                            $disjunction = array(
                                "op" => "|",
                                "c" => $one_type_conditions,
                                "show" => false
                            );
                            $criteria = [];
                            if (count($all_type_conditions) > 0 && count($one_type_conditions) > 0) {
                                $availability = array(
                                    "op" => "&",
                                    "c" => [$conjunction, $disjunction],
                                    "showc" => [true, false]
                                );
                            } else if (count($one_type_conditions) > 0) {
                                $availability = $disjunction;
                            } else {
                                // if disjunct is empty (whether there are prerequisites or not)
                                // the activity cannot be accessed
                                $availability = array(
                                    "op" => "|",
                                    "c" => [
                                        array(
                                            "type" => "profile",
                                            "sf" => "firstname",
                                            "op" => "isequalto",
                                            "v" => "dummy_first_name_to_simulate_false"
                                        )
                                    ],
                                    "show" => false
                                );
                            }
                            $course_section_record->availability = json_encode($availability);
                            $DB->update_record('course_sections', $course_section_record);
                        }
                    }
                } else {
                    echo html_writer::start_tag('p') . "Failed to open zip archive." . html_writer::end_tag('p');
                }
            } else {
                echo html_writer::start_tag('p') . "File is not recognized as a zip archive." . html_writer::end_tag('p');
            }
            echo html_writer::start_tag('p') . "Course recreation is complete." . html_writer::end_tag('p');
            break;
        } else if ($mode === 'update') {

            if ($_FILES['archive']['type'] === "application/zip" || $_FILES['archive']['type'] === "application/x-zip-compressed") {
                $zip = new ZipArchive;
                $open_res = $zip->open($_FILES['archive']['tmp_name']);
                if ($open_res === TRUE) {
                    $location = '/tmp/' . basename($_FILES['archive']['name']);
                    if (substr($location, -4) === ".zip") {
                        $location = substr($location, 0, strlen($location) - 4);
                    }
                    $zip->extractTo($location);
                    $zip->close();
                    /*
                    Wanted to translate something like this (which works):
                    SELECT mdl_clusters.name, mdl_nodes.slug, mdl_nodes.course_sections_id
                    FROM mdl_clusters INNER JOIN mdl_nodes
                    ON mdl_nodes.clusters_id = mdl_clusters.id
                    WHERE mdl_clusters.courseid = 2; 

                    to something like:
                    $sql = "SELECT {clusters}.name, {nodes}.slug, {nodes}.course_sections_id FROM {clusters} INNER JOIN {nodes} ON {nodes}.clusters_id = {clusters}.id WHERE {clusters}.courseid = ?";
                    $id_mapping = $DB->get_records_sql($sql, [intval($_POST['course'])]);

                    But can't really make sense of the output.
                    So I'll do the "JOIN" manually.
                    */
                    $existing_course_clusters = $DB->get_records('clusters', array('courseid' => intval($_POST['course'])));
                    $existing_nodes = array();
                    foreach ($existing_course_clusters as $cluster) {
                        $cluster_nodes = $DB->get_records('nodes', array('clusters_id' => $cluster->id));
                        foreach ($cluster_nodes as $node) {
                            array_push($existing_nodes, array(
                                'cluster_id' => $cluster->id,
                                'cluster_name' => $cluster->name,
                                'node_slug' => $node->slug,
                                'node_id' => $node->id,
                                'course_section_id' => $node->course_sections_id
                            ));
                        }
                    }
                    $unlocking_contents = file_get_contents($location . "/unlocking_conditions.json");
                    $unlocking_conditions = json_decode($unlocking_contents, true);
                    $removed_nodes = array_filter($existing_nodes, function ($node, $idx) use ($unlocking_conditions) {
                        return !in_array($node['cluster_name'] . '__' . $node['node_slug'], array_keys($unlocking_conditions));
                    }, ARRAY_FILTER_USE_BOTH);
                    // there has to be a better way to write this than one operation per node...
                    foreach ($removed_nodes as $removed_node) {
                        $DB->delete_records('course_sections', array('id' => $removed_node['course_section_id']));
                        $DB->delete_records('nodes', array('id' => $removed_node['node_id']));
                    }
                    $sql = "SELECT id FROM {clusters} WHERE courseid = ?";
                    $existing_clusters = $DB->get_records_sql($sql, [intval($_POST['course'])]);
                    $directory_contents = array_diff(scandir($location), array('..', '.'));
                    $upserted_cluster_names = array();
                    foreach ($directory_contents as $file) {
                        if (is_dir($location . "/" . $file)) {
                            array_push($upserted_cluster_names, $file);
                        }
                    }
                    // use IDs, because same cluster name can be used in different courses!
                    $deleted_clusters = array_filter(
                        $existing_clusters,
                        function ($cluster, $idx) use ($upserted_cluster_names) {
                            return !in_array($cluster->name, $upserted_cluster_names);
                        },
                        ARRAY_FILTER_USE_BOTH
                    );
                    echo html_writer::start_tag('p') . "Following old clusters will be deleted:" . html_writer::end_tag('p');
                    var_dump($deleted_clusters);
                    foreach ($deleted_clusters as $deleted_cluster) {
                        $DB->delete_records('clusters', array('id' => $deleted_cluster->id));
                    }
                    $cluster_ids = array();
                    foreach ($directory_contents as $file) {
                        if (is_dir($location . "/" . $file)) {
                            $cluster_record = new StdClass;
                            $cluster_record->name = $file;
                            $cluster_record->courseid = $record->course;
                            $cluster_record->yaml = file_get_contents($location . "/" . $file . "/contents.lc.yaml");
                            // PHP lacks a builtin find function
                            $existing_cluster_record = null;
                            foreach ($existing_clusters as $existing_cluster) {
                                if ($existing_cluster->name === $file) {
                                    $existing_cluster_record = $existing_cluster;
                                    break;
                                }
                            }
                            if (!is_null($existing_cluster_record)) {
                                $cluster_record->id = $existing_cluster_record->id;
                                $DB->update_record('clusters', $cluster_record);
                                $cluster_ids[$file] = $cluster_record->id;
                            } else {
                                $cluster_ids[$file] = $DB->insert_record("clusters", $cluster_record);
                            }
                        }
                    }
                    // previously existing nodes need to be updated and, if there are new assignments, manual completion assignments need to be reset
                    //
                    // new nodes and new sections need to be created, with manual completion assignments, for new cluster+slug combinations
                    //
                    // finally, availability should be reset for *every* section
                    //
                    // NOTE: merge with course creation code once this is all done, should be feasible
                } else {
                    echo html_writer::start_tag('p') . "Failed to open zip archive." . html_writer::end_tag('p');
                }
            } else {
                echo html_writer::start_tag('p') . "File is not recognized as a zip archive." . html_writer::end_tag('p');
            }
        } else {
            echo html_writer::start_tag('p') . "Unknown usage mode." . html_writer::end_tag('p');
        }
}
echo $OUTPUT->footer();
