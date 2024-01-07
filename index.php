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
 * @package     local_greetings
 * @copyright   2022-2023 Vincent Nys <vincent.nys@ap.be>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php'); // Meestal nodig.
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/filelib.php');
require_login();
$FRANKENSTYLE_PLUGIN_NAME = 'local_distance';

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/distance/index.php'));
$PAGE->set_pagelayout('standard'); // Zodat we blocks hebben.
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading(get_string('pluginname', 'local_distance'));

// first section to be created will just be number 1
$next_created_section_number = 1;

function create_course_topic($DB, $course, $key, $topic_section_metadata)
{
    var_dump($key); // for debugging, in case some sections are created and process ends midway
    $preceding_course_module_id_in_section = -1; // first course module will be numbered 0
    $section_number = $next_created_section_number;
    $next_created_section_number += 1;

    $record = new stdClass;
    $record->course = intval($course->id);
    $record->section = $section_number;
    $record->name = $key; // TODO: make this node title instead
    $record->summary = ""; // could add icon and short description here...
    $record->summaryformat = 1;
    $record->sequence = "";
    $record->visible = 1;
    $topic_section_metadata[$key] = array();
    $topic_section_metadata[$key]['moodle_section_id'] = $DB->insert_record('course_sections', $record);

    

    // add final assignment for manual completion
    list($module, $context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'assign', $section_number);
    $data->name = "Manueel aanduiden via het vinkje: \"$key\" is duidelijk";
    $data->course = $course;
    $data->intro = text_to_html("Markeer deze activiteit handmatig als voltooid als $key duidelijk is.", false, false, true);
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
    if ($preceding_course_module_id_in_section >= 0) {
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
    }
    add_moduleinfo($data, $course);

    $preceding_course_module_id_in_section = $data->coursemodule;
    $topic_section_metadata[$key]['manual_completion_assignment_id'] = $data->coursemodule;
    return $topic_section_metadata;
}

echo $OUTPUT->header();
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo html_writer::start_tag('form', array('enctype' => 'multipart/form-data', 'action' => '#', 'method' => 'POST'));

        $courses = $DB->get_records('course', []);
        echo html_writer::start_tag('div');
        echo html_writer::start_tag('label', array('for' => 'course-select-dropdown'));
        echo "Import into course:";
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

        echo html_writer::start_tag('input', array('type' => 'submit', 'value' => 'Recreate course'));
        echo html_writer::end_tag('form');
        break;
    case 'POST':
        $course = $DB->get_record('course', ['id' => intval($_POST['course'])]);
        $DB->delete_records('course_modules', array('course' => $course->id));
        $DB->delete_records('course_sections', array('course' => $course->id));
        if ($_FILES['archive']['type'] === "application/zip") {
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
                $unlocking_conditions = json_decode($unlocking_contents, true);
                // intended keys: moodle_section_id, manual_completion_assignment_id
                $topic_section_metadata = array();
                foreach ($unlocking_conditions as $key => $value) {
                    // TODO: do I really need to mutate *and* return?
                    $topic_section_metadata = create_course_topic($DB, $course, $key, $topic_section_metadata);
                }
                $namespaced_id_to_completion_id = function ($namespaced_id) use ($topic_section_metadata) {
                    return $topic_section_metadata[$namespaced_id]['manual_completion_assignment_id'];
                };
                $completion_id_to_condition = function ($cm_id) {
                    return array(
                        "type" => "completion",
                        "cm" => $cm_id,
                        "e" => 1
                    );
                };
                foreach ($unlocking_conditions as $key => $completion_criteria) {
                    if ($completion_criteria) {
                        $course_section_id = $topic_section_metadata[$key]['moodle_section_id'];
                        $course_section_record = $DB->get_record('course_sections', ['id' => $course_section_id]);
                        $all_type_dependency_completion_ids = array_map($namespaced_id_to_completion_id, $completion_criteria['allOf']);
                        $one_type_dependency_completion_ids = array_map($namespaced_id_to_completion_id, $completion_criteria['oneOf']);
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
                                "c" => [array(
                                    "type" => "profile",
                                    "sf" => "firstname",
                                    "op" => "isequalto",
                                    "v" => "dummy_first_name_to_simulate_false"
                                )],
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
        echo html_writer::start_tag('p') . "Done handling POST request." . html_writer::end_tag('p');
        break;
}
echo $OUTPUT->footer();
