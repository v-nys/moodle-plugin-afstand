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

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/distance/index.php'));
$PAGE->set_pagelayout('standard'); // Zodat we blocks hebben.
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading(get_string('pluginname', 'local_distance'));

function create_course_topic($DB, $course, $key, $course_module_ids, $topic_offset)
{
    $offset = 2; // next available section number, can compute this later on...
    var_dump($key); // for debugging, indicates which section is an issue
    $record = new stdClass;
    $record->course = 2;
    $record->section = $offset + $topic_offset;
    $record->name = $key;
    $record->summary = "";
    $record->summaryformat = 1;
    $record->sequence = "";
    $record->visible = 1;
    $course_module_ids[$key] = $DB->insert_record('course_sections', $record);
    return $course_module_ids;
}

echo $OUTPUT->header();
switch($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo html_writer::start_tag('form', array('enctype' => 'multipart/form-data', 'action' => '#', 'method' => 'POST'));
        echo html_writer::start_tag('input', array('type' => 'file', 'id' => 'archive-upload-button', 'name' => 'archive'));
        echo html_writer::end_tag('input');
        echo html_writer::start_tag('input', array('type' => 'submit', 'value' => 'Upload archive'));
        echo html_writer::end_tag('form');
        break;
    case 'POST':
        echo html_writer::start_tag('p') . "Handling POST request." . html_writer::end_tag('p');
        $uploaddir = '/tmp/uploads';
        $uploadedfile = $uploaddir . basename($_FILES['archive']['name']);
        // Cursus voorlopig hardgecodeerd op 2
        // Zou goed idee zijn hier dropdown ofzo voor te geven...
        $course = $DB->get_record('course', ['id' => 2]);
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
                echo html_writer::start_tag('p') . "Check /tmp for contents." . html_writer::end_tag('p');
                // TODO: maak nu de topics aan op basis van unlocking_conditions.json
                // 1. (x) deserialize JSON
                // 2. (x) itereer over de topics (volgorde? is output wel gesorteerd? denk het niet...)
                // 3. (x) maak een moodle topic aan per topic, zonder extra voorwaarden
                // 4. (-) voeg telkens een "afwerkopdracht" toe (grep desktop naar beschrijvende tekst)
                // 5. (-) voeg voorwaarden toe via tweede foreach lus
                // 6. (-) zorg dat dit demonstreert dat het hele spel werkt
                $unlocking_contents = file_get_contents($location . "unlocking_conditions.json");
                $unlocking_conditions = json_decode($unlocking_contents, true);
                $course_module_ids = array();
                $section_offset = 0;
                // note: PHP associative arrays are ordered
                // value should be an associative array with entries allOf and oneOf
                foreach($unlocking_conditions as $key => $value) {
                    $course_module_ids = create_course_topic($DB, $course, $key, $course_module_ids, $section_offset);
                    $section_offset++;
                    var_dump($course_module_ids);
                }
                foreach($unlocking_conditions as $key => $value) {
                    // TODO: add completion conditions by performing lookup in $course_module_ids
                }
            }
            else {
                echo html_writer::start_tag('p') . "Failed to open zip archive." . html_writer::end_tag('p');
            }
        }
        else {
            echo html_writer::start_tag('p') . "File is not recognized as a zip archive." . html_writer::end_tag('p');
        }
        echo html_writer::start_tag('p') . "Done handling POST request." . html_writer::end_tag('p');
        break;
}
echo $OUTPUT->footer();
