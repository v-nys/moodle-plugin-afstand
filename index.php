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
require_login(); // Moet van code checker...
$FRANKENSTYLE_PLUGIN_NAME = 'local_distance';

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/distance/index.php'));
$PAGE->set_pagelayout('standard'); // Zodat we blocks hebben.
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading(get_string('pluginname', 'local_distance'));

function create_course_topic($DB, $course, $key, $course_module_ids, $topic_offset)
{
    $preceding_course_module_id_in_section = -1;
    $offset = 2; // next available section number, can compute this later on...
    var_dump($key); // for debugging, indicates which section is an issue
    $record = new stdClass;
    $record->course = intval($course->id);
    $record->section = $offset + $topic_offset;
    $record->name = $key;
    $record->summary = "";
    $record->summaryformat = 1;
    $record->sequence = "";
    $record->visible = 1;
    $course_module_ids[$key] = array();
    $course_module_ids[$key]['moodle_id'] = $DB->insert_record('course_sections', $record);
    // add assignment for manual completion
    list($module, $context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'assign', $offset + $topic_offset);
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
    $data->completionview = COMPLETION_VIEW_NOT_REQUIRED; // means viewing does not count towards completion, which we want
    // note: only possible to track completion for enrolled (even admin) users!
    $data->completion = COMPLETION_TRACKING_MANUAL;
    $data->completionexpected = 0;
    $data->completionunlocked = "1"; // see add_moduleinfo definition
    $data->completiongradeitemnumber = NULL;
    // NOTE: won't be the case until we add links, assignments,...
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
    $course_module_ids[$key]['manual_completion_id'] = $data->coursemodule;
    return $course_module_ids;
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

        echo html_writer::start_tag('div', array());
        echo html_writer::start_tag('label', array('for' => 'empty-course-checkbox'));
        echo "Empty existing course:";
        echo html_writer::end_tag('label');
        echo html_writer::start_tag('input', array('type' => 'checkbox', 'checked' => true, 'id' => 'empty-course-checkbox', 'name' => 'empty-course', 'value' => 'yes'));
        echo html_writer::end_tag('input');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('input', array('type' => 'submit', 'value' => 'Create course'));
        echo html_writer::end_tag('form');
        break;
    case 'POST':
        echo html_writer::start_tag('p') . "Handling POST request." . html_writer::end_tag('p');
        $uploaddir = '/tmp/uploads';
        $uploadedfile = $uploaddir . basename($_FILES['archive']['name']);
        $course = $DB->get_record('course', ['id' => intval($_POST['course'])]);
        if (isset($_POST['empty-course']) && $_POST['empty-course'] == 'yes') {
            $DB->delete_records('course_modules', array('course' => $course->id));
            $DB->delete_records('course_sections', array('course' => $course->id));
        }
        if ($_FILES['archive']['type'] === "application/zip") {
            echo html_writer::start_tag('p') . "File is recognized as a zip archive." . html_writer::end_tag('p');
            $zip = new ZipArchive;
            $open_res = $zip->open($_FILES['archive']['tmp_name']);
            if ($open_res === TRUE) {
                $location = '/tmp/' . basename($_FILES['archive']['name']);
                if (substr($location, -4) === ".zip") {
                    $location = substr($location, 0, strlen($location) - 4);
                }
                $zip->extractTo($location);
                $zip->close();
                echo html_writer::start_tag('p') . "Archive extracted to /tmp." . html_writer::end_tag('p');

                // create a section for the course "map"
                $record = new stdClass;
                $record->course = intval($course->id);
                $maps_section_number = 1; // TODO: take into account offset...
                $record->section = $maps_section_number; 
                $record->name = "overview";
                $record->summary = "Hier vind je \"kaarten\" van de verschillende onderwerpen die in de cursus behandeld worden.";
                $record->summaryformat = 1;
                $record->sequence = "";
                $record->visible = 1;
                $maps_section_id = $DB->insert_record('course_sections', $record);
                $absolute_svg_paths = glob($location . "/*.svg"); // produces absolute paths
                $fs = get_file_storage();
                $map_files = array();
                foreach ($absolute_svg_paths as $absolute_svg_path) {
                    $fileinfo = [
                        'contextid' => $context->id,
                        'component' => $FRANKENSTYLE_PLUGIN_NAME,
                        'filearea' => 'navigation', // not a fixed name, see https://moodledev.io/docs/apis/subsystems/files#naming-file-areas
                        'itemid' => 0, // explanation at https://moodledev.io/docs/apis/subsystems/files#file-areas is still a little hazy, but there is only one navigation area per course...
                        'filepath' => $absolute_svg_path . "/", // doesn't seem to have anything to do with actual file system based on docs above
                        'filename' => basename($absolute_svg_path)
                    ];
                    $storedfile = $fs->create_file_from_pathname($fileinfo, $absolute_svg_path);
                    $map_files[basename($absolute_svg_path,".svg")] = $storedfile;
                }
                // TODO: don't try to create resources for the files
                // this is pretty unclear
                // instead, look at https://moodledev.io/docs/apis/subsystems/files#serving-files-to-users
                list($module, $context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'resource', $maps_section_number);
                $data->name = "kaart";
                $data->introformat = FORMAT_HTML;
                add_moduleinfo($data, $course);
                $unlocking_contents = file_get_contents($location . "/unlocking_conditions.json");
                $unlocking_conditions = json_decode($unlocking_contents, true);
                // intended keys: moodle_id, manual_completion_id
                $course_module_ids = array();
                $section_offset = 0;
                foreach ($unlocking_conditions as $key => $value) {
                    // TODO: do I really need to mutate *and* return?
                    $course_module_ids = create_course_topic($DB, $course, $key, $course_module_ids, $section_offset);
                    $section_offset++;
                }
                $namespaced_id_to_completion_id = function ($namespaced_id) use ($course_module_ids) {
                    return $course_module_ids[$namespaced_id]['manual_completion_id'];
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
                        $course_section_id = $course_module_ids[$key]['moodle_id'];
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
