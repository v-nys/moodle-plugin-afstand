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

function create_course_topic($DB, $course, $node, $course_module_ids, $index)
{
    $offset = 2; // next available section number, can compute this later on...

    // create the section itself
    $title = $node['id'];
    var_dump($title); // zo weet ik als het mis loopt in een sectie de welke
    $record = new stdClass;
    $record->course = 2; // test course
    $record->section = $offset + $index; // nummer van de nieuwe sectie in de cursus
    $record->name = $title;
    $record->summary = "";
    $record->summaryformat = 1;
    $record->sequence = "";
    $record->visible = 1;
    $course_module_ids[$node['id']]['id'] = $DB->insert_record('course_sections', $record);

    // add a URL for the course text
    $url_or_urls = $node['url'];
    if ($url_or_urls != "no-link") {
        if (is_array($url_or_urls)) {
            $counter = 1;
            foreach($url_or_urls as $url) {
                list($module, $context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'url', $offset + $index);
            $data->name = "Link $counter $title";
            $data->parameter = array();
            $data->variable = array();
            $data->display = 3; // open in new window / tab
            $data->externalurl = $url;
            $data->printintro = false;
            $data->completionexpected = false;
            add_moduleinfo($data, $course);
            $counter++;
            }
        } else {
            list($module, $context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'url', $offset + $index);
            $data->name = "Link $title";
            $data->parameter = array();
            $data->variable = array();
            $data->display = 3; // open in new window / tab
            $data->externalurl = $url_or_urls;
            $data->printintro = false;
            $data->completionexpected = false;
            add_moduleinfo($data, $course);
        }
    }


    foreach ($node['videos'] as $video_link) {
        list($module, $context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'url', $offset + $index);
        $data->name = "Filmpje $title";
        $data->parameter = array();
        $data->variable = array();
        $data->display = 0;
        $data->externalurl = $video_link;
        $data->printintro = false;
        $data->completionexpected = false;
        add_moduleinfo($data, $course);
    }

    // add a quiz
    // note that questions and grading need to be entered manually
    // disabled, as quizes proved to be problematic
    /*
    list($module, $context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'quiz', $offset + $index);
    $data->name = "Quiz $title";
    $data->quizpassword = '';
    $times = array('during', 'immediately', 'open', 'closed');
    $aspects = array('attempt', 'correctness', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback');
    foreach ($times as $time) {
        foreach ($aspects as $aspect) {
            $component = $aspect . $time;
            $data->$component = true;
        }
    }
    $data->overallfeedbackduring = false;
    $data->completionusegrade = false;
    $data->completionpass = true;
    $data->questiondecimalpoints = 2;
    $data->decimalpoints = 2;
    add_moduleinfo($data, $course);
    $course_module_ids[$node['id']]['quiz'] = $data->coursemodule;
    */

    //$course_module_ids[$node['id']]['quiz'] = 

    /* add assignments
     * assumes a folder containing assignments has been uploaded to private files
     * note: you can upload a zip, click on its icon and extract it
     * this makes it possible to "upload a folder"
     * DON'T FORGET TO SAVE CHANGES AFTER UNZIPPING!!! */
    $counter = 0;
    $fs = get_file_storage();
    foreach ($node['exercises'] as $mdhtml) {
        var_dump($mdhtml);
        $counter = $counter + 1;
        // step 0: prep file metadata
        $path_components = explode('/', $mdhtml);
        $without_filename = array_slice($path_components, 0, count($path_components) - 1);
        $filepath = implode('/', $without_filename);
        $filepath .= '/';
        $filename = $path_components[count($path_components) - 1];
        $admin_user_id = 2;
        // just assuming a match, would be cleaner to use a hash though
        $original_file = $DB->get_record('files', array('filepath' => $filepath, 'filename' => $filename, 'filearea' => 'private', 'userid' => $admin_user_id), '*', IGNORE_MULTIPLE);
        // step 1: creating course files from private files
        $filerecord = array();
        $filerecord['filepath'] = '/'; // dit is voor de **nieuwe** file
        $filerecord['filename'] = str_replace('md.html', 'html', $filename); // dit is voor de **nieuwe** file
        $filerecord['component'] = 'user';
        $filerecord['filearea'] = 'draft';
        $filerecord['itemid'] = file_get_unused_draft_itemid();
        $filerecord['license'] = 'unknown';
        $filerecord['author'] = 'script voor automatische aanmaak';
        $filerecord['contextid'] = 5;
        $filerecord['timecreated'] = 1660045452;
        $filerecord['timemodified'] = 1660045452;
        $filerecord['userid'] = $admin_user_id;
        $filerecord['sortorder'] = 0;
        $filerecord['source'] = null;
        $storedfile = get_file_storage()->create_file_from_storedfile($filerecord, intval($original_file->id)); // TODO: dit aanpassen naar juiste ID
        // step 2: making an assignment based on the file
        list($module, $context, $cw, $cmrec, $data) = prepare_new_moduleinfo_data($course, 'assign', $offset + $index);
        $data->name = "Opdracht $counter $title";
        $data->course = $course;
        $data->intro = '<p dir="ltr" style="text-align: left;">test 1<br></p>';
        $data->introformat = 1;
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
        $data->introattachments = $storedfile->get_itemid();
        add_moduleinfo($data, $course);
        array_push($course_module_ids[$node['id']]['assignments'], $data->coursemodule);
    }
    return $course_module_ids;
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/distance/index.php'));
$PAGE->set_pagelayout('standard'); // Zodat we blocks hebben.

$PAGE->set_title($SITE->fullname);
$PAGE->set_heading(get_string('pluginname', 'local_distance'));


// Cursus voorlopig hardgecodeerd op 2.
$course = $DB->get_record('course', ['id' => 2]);
// IMPORTANT: nodes should be specified according to their partial order!
$structure = json_decode(
    '{
        "motivated_by_any_of": {
            "db__basisstructuren_relationele_database": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__essentiele_datatypes_mysql": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__export": [
                "friendface__intent_speedup"
            ],
            "db__hash_functies": [
                "friendface__intent_real_login"
            ],
            "db__inleiding_tot_mysql_taal": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__installatie_mysql_via_docker": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__setup_work_environment_intent_stack_with_mysql",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__installatie_mysql_workbench": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__setup_work_environment_intent_stack_with_mysql",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__mysql_aggregatiefuncties": [
                "friendface__intent_statistics",
                "friendface__intent_random_reactions"
            ],
            "db__mysql_alle_clausules_samen": [
                "friendface__intent_statistics"
            ],
            "db__mysql_alle_joins": [],
            "db__mysql_alter_instructie": [
                "friendface__intent_generalizing_sneezes"
            ],
            "db__mysql_booleaanse_operatoren": [],
            "db__mysql_cascade": [
                "friendface__intent_fixing_data_management"
            ],
            "db__mysql_control_flow": [
                "friendface__intent_random_memberships",
                "friendface__intent_deleting_profile",
                "friendface__intent_mock_sneezes"
            ],
            "db__mysql_create_instructie": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__mysql_cursors": [
                "friendface__intent_random_memberships"
            ],
            "db__mysql_ddl_dml_et_al": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__mysql_delete_instructie": [
                "friendface__intent_admin_page_data_management",
                "friendface__intent_fixing_data_management"
            ],
            "db__mysql_distinct": [],
            "db__mysql_drop_instructie": [],
            "db__mysql_een_op_een": [
                "friendface__intent_adding_clubs",
                "friendface__intent_showing_memberships",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__mysql_een_op_veel": [
                "friendface__intent_adding_clubs",
                "friendface__intent_showing_memberships",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__mysql_enum": [
                "friendface__intent_reactions"
            ],
            "db__mysql_erd_design": [],
            "db__mysql_error_handling": [
                "friendface__intent_random_memberships",
                "friendface__intent_deleting_profile"
            ],
            "db__mysql_functies": [
                "friendface__intent_real_login",
                "friendface__intent_statistics",
                "friendface__intent_random_reactions"
            ],
            "db__mysql_generated_column": [
                "friendface__intent_mirrored_friendships"
            ],
            "db__mysql_group_by": [
                "friendface__intent_statistics"
            ],
            "db__mysql_having": [
                "friendface__intent_statistics"
            ],
            "db__mysql_insert_instructie": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__mysql_join_een_op_een_of_op_veel": [
                "friendface__intent_showing_memberships",
                "friendface__intent_showing_all_sneezes"
            ],
            "db__mysql_join_veel_op_veel": [
                "friendface__intent_showing_memberships"
            ],
            "db__mysql_left_join": [],
            "db__mysql_like_operator": [
                "friendface__intent_statistics"
            ],
            "db__mysql_limit": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_mock_sneezes"
            ],
            "db__mysql_limit_offset": [
                "friendface__intent_paginated_timeline"
            ],
            "db__mysql_null": [
                "friendface__intent_generalizing_sneezes"
            ],
            "db__mysql_object_access_control": [],
            "db__mysql_order_asc_desc": [
                "friendface__intent_statistics",
                "friendface__intent_simple_admin_page"
            ],
            "db__mysql_primaire_sleutels": [
                "friendface__intent_adding_clubs",
                "friendface__intent_showing_memberships",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__mysql_select_basis": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__mysql_select_into": [
                "friendface__intent_mock_sneezes"
            ],
            "db__mysql_sessievariabelen": [
                "friendface__intent_random_reactions"
            ],
            "db__mysql_signal": [
                "friendface__intent_data_integrity"
            ],
            "db__mysql_sleutels": [
                "friendface__intent_adding_clubs",
                "friendface__intent_showing_memberships",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__mysql_stored_function": [
                "friendface__intent_mirrored_friendships"
            ],
            "db__mysql_stored_procedure_syntax_en_vars": [
                "friendface__intent_showing_friends_sneezes",
                "friendface__intent_random_memberships",
                "friendface__intent_deleting_profile",
                "friendface__intent_mock_sneezes"
            ],
            "db__mysql_stored_programs": [
                "friendface__intent_showing_friends_sneezes",
                "friendface__intent_random_memberships",
                "friendface__intent_deleting_profile",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_mock_sneezes"
            ],
            "db__mysql_subqueries": [
                "friendface__intent_random_reactions"
            ],
            "db__mysql_temp_tables": [],
            "db__mysql_transacties": [
                "friendface__intent_deleting_profile"
            ],
            "db__mysql_trigger": [
                "friendface__intent_data_integrity"
            ],
            "db__mysql_union_union_all": [
                "friendface__intent_mirrored_friendships"
            ],
            "db__mysql_unique_constraint": [
                "friendface__intent_mirrored_friendships"
            ],
            "db__mysql_updatable_views": [],
            "db__mysql_update_instructie": [
                "friendface__intent_statistics",
                "friendface__intent_generalizing_sneezes",
                "friendface__intent_admin_page_data_management"
            ],
            "db__mysql_update_join": [],
            "db__mysql_veel_op_veel": [
                "friendface__intent_adding_clubs",
                "friendface__intent_showing_memberships"
            ],
            "db__mysql_vergelijkingen": [],
            "db__mysql_views": [
                "friendface__intent_showing_friends_sneezes",
                "friendface__intent_random_memberships",
                "friendface__intent_deleting_profile",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_mock_sneezes"
            ],
            "db__mysql_vreemde_sleutels": [
                "friendface__intent_adding_clubs",
                "friendface__intent_showing_memberships",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__wat_is_een_databank": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__introduction",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__setup_work_environment_intent_stack_with_mysql",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "db__wat_is_een_relationele_database": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__setup_work_environment_intent_stack_with_mysql",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "express__authenticatie_en_autorisatie": [],
            "express__basis": [
                "friendface__setup_work_environment_intent_express",
                "friendface__intent_simple_admin_page"
            ],
            "express__body_parser": [],
            "express__cookies": [
                "friendface__intent_specific_user_login_page"
            ],
            "express__cors": [
                "friendface__intent_editing_data"
            ],
            "express__ejs": [
                "friendface__intent_simple_admin_page"
            ],
            "express__ejs_advanced": [],
            "express__env_variabelen": [],
            "express__jwt_algemeen": [
                "friendface__intent_specific_user_login_page"
            ],
            "express__jwt_npm_package": [
                "friendface__intent_specific_user_login_page"
            ],
            "express__jwt_oefening": [],
            "express__mysql_connector": [
                "friendface__intent_simple_admin_page"
            ],
            "express__oauth": [],
            "express__post_requests": [
                "friendface__intent_admin_page_data_management"
            ],
            "express__static_files": [
                "friendface__intent_profile_pictures"
            ],
            "express__wat_is_een_backend_framework": [
                "friendface__introduction",
                "friendface__introduction",
                "friendface__setup_work_environment_intent_express",
                "friendface__intent_simple_admin_page"
            ],
            "friendface__adding_friendships": [
                "friendface__implementation_showing_memberships"
            ],
            "friendface__completing_the_app": [
                "friendface__implementation_profile_pictures"
            ],
            "friendface__gitlab": [
                "friendface__implementation_experimentation",
                "friendface__intent_admin_page_data_management"
            ],
            "friendface__implementation_adding_clubs": [
                "friendface__intent_adding_clubs"
            ],
            "friendface__implementation_admin_page_data_management": [
                "friendface__intent_admin_page_data_management"
            ],
            "friendface__implementation_barebones_user_profiles": [
                "friendface__intent_barebones_user_profiles"
            ],
            "friendface__implementation_data_integrity": [
                "friendface__intent_data_integrity"
            ],
            "friendface__implementation_deleting_profile": [
                "friendface__intent_deleting_profile"
            ],
            "friendface__implementation_editing_data": [
                "friendface__intent_editing_data"
            ],
            "friendface__implementation_env_secrets": [
                "friendface__intent_env_secrets"
            ],
            "friendface__implementation_experimentation": [
                "friendface__intent_experimentation"
            ],
            "friendface__implementation_fixing_data_management": [
                "friendface__intent_fixing_data_management"
            ],
            "friendface__implementation_generalizing_sneezes": [
                "friendface__intent_generalizing_sneezes"
            ],
            "friendface__implementation_mirrored_friendships": [
                "friendface__intent_mirrored_friendships"
            ],
            "friendface__implementation_mock_sneezes": [
                "friendface__intent_mock_sneezes"
            ],
            "friendface__implementation_mysql_scripts_startup": [
                "friendface__intent_mysql_scripts_startup"
            ],
            "friendface__implementation_paginated_timeline": [
                "friendface__intent_paginated_timeline"
            ],
            "friendface__implementation_posting_sneezes": [
                "friendface__intent_posting_sneezes"
            ],
            "friendface__implementation_profile_pictures": [
                "friendface__intent_profile_pictures"
            ],
            "friendface__implementation_random_friendships": [
                "friendface__intent_random_friendships"
            ],
            "friendface__implementation_random_memberships": [
                "friendface__intent_random_memberships"
            ],
            "friendface__implementation_random_reactions": [
                "friendface__intent_random_reactions"
            ],
            "friendface__implementation_reactions": [
                "friendface__intent_reactions"
            ],
            "friendface__implementation_real_login": [
                "friendface__intent_real_login"
            ],
            "friendface__implementation_showing_all_sneezes": [
                "friendface__intent_showing_all_sneezes"
            ],
            "friendface__implementation_showing_friends_sneezes": [
                "friendface__intent_showing_friends_sneezes"
            ],
            "friendface__implementation_showing_memberships": [
                "friendface__intent_showing_memberships"
            ],
            "friendface__implementation_simple_admin_page": [
                "friendface__intent_simple_admin_page"
            ],
            "friendface__implementation_specific_user_login_page": [
                "friendface__intent_specific_user_login_page"
            ],
            "friendface__implementation_speedup": [
                "friendface__intent_speedup"
            ],
            "friendface__implementation_statistics": [
                "friendface__intent_statistics"
            ],
            "friendface__intent_adding_clubs": [
                "friendface__implementation_showing_all_sneezes"
            ],
            "friendface__intent_admin_page_data_management": [
                "friendface__implementation_simple_admin_page"
            ],
            "friendface__intent_barebones_user_profiles": [
                "friendface__setup_work_environment_implementation_version_control"
            ],
            "friendface__intent_data_integrity": [
                "friendface__implementation_generalizing_sneezes"
            ],
            "friendface__intent_deleting_profile": [
                "friendface__implementation_editing_data"
            ],
            "friendface__intent_editing_data": [
                "friendface__implementation_paginated_timeline"
            ],
            "friendface__intent_env_secrets": [
                "friendface__implementation_specific_user_login_page"
            ],
            "friendface__intent_experimentation": [
                "friendface__implementation_mysql_scripts_startup"
            ],
            "friendface__intent_fixing_data_management": [
                "friendface__implementation_posting_sneezes"
            ],
            "friendface__intent_generalizing_sneezes": [
                "friendface__implementation_mirrored_friendships"
            ],
            "friendface__intent_mirrored_friendships": [
                "friendface__adding_friendships"
            ],
            "friendface__intent_mock_sneezes": [
                "friendface__implementation_showing_friends_sneezes"
            ],
            "friendface__intent_mysql_scripts_startup": [
                "friendface__implementation_barebones_user_profiles"
            ],
            "friendface__intent_paginated_timeline": [
                "friendface__implementation_real_login"
            ],
            "friendface__intent_posting_sneezes": [
                "friendface__implementation_env_secrets"
            ],
            "friendface__intent_profile_pictures": [
                "friendface__implementation_deleting_profile"
            ],
            "friendface__intent_random_friendships": [
                "friendface__implementation_random_memberships"
            ],
            "friendface__intent_random_memberships": [
                "friendface__implementation_speedup"
            ],
            "friendface__intent_random_reactions": [
                "friendface__implementation_reactions"
            ],
            "friendface__intent_reactions": [
                "friendface__implementation_mock_sneezes"
            ],
            "friendface__intent_real_login": [
                "friendface__implementation_random_friendships"
            ],
            "friendface__intent_showing_all_sneezes": [
                "friendface__implementation_fixing_data_management"
            ],
            "friendface__intent_showing_friends_sneezes": [
                "friendface__implementation_data_integrity"
            ],
            "friendface__intent_showing_memberships": [
                "friendface__implementation_adding_clubs"
            ],
            "friendface__intent_simple_admin_page": [
                "friendface__implementation_experimentation"
            ],
            "friendface__intent_specific_user_login_page": [
                "friendface__implementation_statistics"
            ],
            "friendface__intent_speedup": [
                "friendface__implementation_random_reactions"
            ],
            "friendface__intent_statistics": [
                "friendface__implementation_admin_page_data_management"
            ],
            "friendface__introduction": [],
            "friendface__setup_work_environment_implementation_express": [
                "friendface__setup_work_environment_intent_express"
            ],
            "friendface__setup_work_environment_implementation_stack_with_mysql": [
                "friendface__setup_work_environment_intent_stack_with_mysql"
            ],
            "friendface__setup_work_environment_implementation_version_control": [
                "friendface__setup_work_environment_intent_version_control"
            ],
            "friendface__setup_work_environment_intent_express": [
                "friendface__introduction"
            ],
            "friendface__setup_work_environment_intent_stack_with_mysql": [
                "friendface__setup_work_environment_implementation_express"
            ],
            "friendface__setup_work_environment_intent_version_control": [
                "friendface__setup_work_environment_implementation_stack_with_mysql"
            ],
            "git__git_add": [
                "friendface__intent_env_secrets",
                "friendface__setup_work_environment_intent_version_control",
                "friendface__intent_experimentation"
            ],
            "git__git_basisbegrippen": [
                "friendface__intent_env_secrets",
                "friendface__setup_work_environment_intent_version_control",
                "friendface__intent_experimentation"
            ],
            "git__git_branch": [
                "friendface__intent_experimentation"
            ],
            "git__git_checkout": [
                "friendface__intent_experimentation"
            ],
            "git__git_clone": [],
            "git__git_commit": [
                "friendface__intent_env_secrets",
                "friendface__setup_work_environment_intent_version_control",
                "friendface__intent_experimentation"
            ],
            "git__git_init": [
                "friendface__intent_env_secrets",
                "friendface__setup_work_environment_intent_version_control",
                "friendface__intent_experimentation"
            ],
            "git__git_log": [
                "friendface__intent_experimentation"
            ],
            "git__git_merge": [],
            "git__git_pull": [
                "friendface__intent_experimentation"
            ],
            "git__git_push": [
                "friendface__setup_work_environment_intent_version_control",
                "friendface__intent_experimentation"
            ],
            "git__git_remote": [
                "friendface__setup_work_environment_intent_version_control",
                "friendface__intent_experimentation"
            ],
            "git__git_rm": [],
            "git__git_status": [],
            "git__gitignore": [
                "friendface__intent_env_secrets",
                "friendface__setup_work_environment_intent_version_control"
            ],
            "git__installatie_prerequisites": [
                "friendface__intent_env_secrets",
                "friendface__setup_work_environment_intent_version_control",
                "friendface__intent_experimentation"
            ],
            "git__wat_is_lokaal_versiebeheer": [
                "friendface__intent_env_secrets",
                "friendface__setup_work_environment_intent_version_control",
                "friendface__intent_experimentation"
            ],
            "git__wat_is_versiebeheer": [
                "friendface__intent_env_secrets",
                "friendface__setup_work_environment_intent_version_control",
                "friendface__introduction",
                "friendface__intent_experimentation"
            ],
            "operationeel__absoluut_vs_relatief": [
                "friendface__setup_work_environment_intent_express",
                "friendface__intent_simple_admin_page"
            ],
            "operationeel__compose_file": [
                "friendface__setup_work_environment_intent_stack_with_mysql"
            ],
            "operationeel__containers_starten_docker": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__setup_work_environment_intent_express",
                "friendface__setup_work_environment_intent_stack_with_mysql",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "operationeel__env_variabelen": [
                "friendface__intent_env_secrets"
            ],
            "operationeel__hashing": [
                "friendface__intent_real_login"
            ],
            "operationeel__installatie_docker": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__setup_work_environment_intent_express",
                "friendface__setup_work_environment_intent_stack_with_mysql",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "operationeel__installatie_docker_compose": [
                "friendface__setup_work_environment_intent_stack_with_mysql"
            ],
            "operationeel__letsencrypt": [],
            "operationeel__reproduceerbare_omgevingen": [],
            "operationeel__reproduceerbare_stacks": [],
            "operationeel__rest_api": [],
            "operationeel__salting": [
                "friendface__intent_real_login"
            ],
            "operationeel__ssh_config": [],
            "operationeel__ssh_file_transfer": [],
            "operationeel__ssh_remote_login": [],
            "operationeel__ssh_sleutels_genereren": [],
            "operationeel__ssh_werking": [],
            "operationeel__stack_starten": [
                "friendface__setup_work_environment_intent_stack_with_mysql"
            ],
            "operationeel__thunder_client": [],
            "operationeel__vscode_remote_dev_compose": [
                "friendface__setup_work_environment_intent_stack_with_mysql"
            ],
            "operationeel__vscode_remote_dev_post_start_command": [
                "friendface__setup_work_environment_intent_express"
            ],
            "operationeel__vscode_remote_dev_single_container": [
                "friendface__setup_work_environment_intent_express",
                "friendface__setup_work_environment_intent_stack_with_mysql"
            ],
            "operationeel__wat_is_docker": [
                "friendface__intent_paginated_timeline",
                "friendface__intent_adding_clubs",
                "friendface__intent_statistics",
                "friendface__intent_reactions",
                "friendface__intent_barebones_user_profiles",
                "friendface__introduction",
                "friendface__intent_showing_memberships",
                "friendface__intent_generalizing_sneezes",
                "friendface__setup_work_environment_intent_express",
                "friendface__setup_work_environment_intent_stack_with_mysql",
                "friendface__intent_admin_page_data_management",
                "friendface__intent_mirrored_friendships",
                "friendface__intent_speedup",
                "friendface__intent_fixing_data_management",
                "friendface__intent_showing_all_sneezes",
                "friendface__intent_simple_admin_page",
                "friendface__intent_mock_sneezes",
                "friendface__intent_posting_sneezes"
            ],
            "operationeel__wat_is_docker_compose": [
                "friendface__setup_work_environment_intent_stack_with_mysql"
            ]
        },
        "nodes": {
            "db__basisstructuren_relationele_database": {
                "URLs": [
                    {
                        "description": "Uitleg (tekst)",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/databanken/basisstructuren-van-een-relationele-databank"
                    }
                ],
                "title": "Basisstructuren van een relationele database"
            },
            "db__essentiele_datatypes_mysql": {
                "URLs": [
                    {
                        "description": "Uitleg (tekst)",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/deeltalen/ddl/datatypes"
                    }
                ],
                "assignments": [
                    {
                        "attachments": [
                            "oefeningen/MySQL/Essentieel/debug-script-1.sql",
                            "oefeningen/MySQL/Essentieel/debug-script-2.sql",
                            "oefeningen/MySQL/Essentieel/debug-script-3.sql"
                        ],
                        "description": "Oefening 1",
                        "path": "oefeningen/MySQL/Essentieel/essentieel-1.md.html"
                    },
                    {
                        "description": "Oefening 2",
                        "path": "oefeningen/MySQL/Essentieel/essentieel-2.md.html"
                    },
                    {
                        "description": "Oefening 3",
                        "path": "oefeningen/MySQL/Essentieel/essentieel-3.md.html"
                    },
                    {
                        "description": "Oefening 4",
                        "path": "oefeningen/MySQL/Essentieel/essentieel-4.md.html"
                    }
                ],
                "title": "Essenti\u00eble datatypes"
            },
            "db__export": {},
            "db__hash_functies": {},
            "db__inleiding_tot_mysql_taal": {
                "URLs": [
                    {
                        "description": "Uitleg (tekst)",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/deeltalen#structuur-van-mysql"
                    },
                    {
                        "description": "Uitleg (filmpje)",
                        "url": "https://youtu.be/jGGsXvdEYyI"
                    },
                    {
                        "description": "Voorbeelden (tekst)",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/deeltalen#voorbeeldinstructies-mysql"
                    },
                    {
                        "description": "Voorbeelden (filmpje)",
                        "url": "https://youtu.be/c4nAguBLFAc"
                    }
                ],
                "title": "Inleiding tot de MySQL-taal"
            },
            "db__installatie_mysql_via_docker": {
                "assignments": [
                    {
                        "description": "Installeren MySQL via Docker",
                        "path": "oefeningen/MySQL/installeren-mysql-in-container.md"
                    }
                ],
                "title": "Installatie MySQL via Docker"
            },
            "db__installatie_mysql_workbench": {
                "title": "Installatie MySQL Workbench"
            },
            "db__mysql_aggregatiefuncties": {},
            "db__mysql_alle_clausules_samen": {},
            "db__mysql_alle_joins": {},
            "db__mysql_alter_instructie": {},
            "db__mysql_booleaanse_operatoren": {},
            "db__mysql_cascade": {},
            "db__mysql_control_flow": {},
            "db__mysql_create_instructie": {
                "URLs": [
                    {
                        "description": "Aanmaken van nieuwe databases en tabellen",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/deeltalen/ddl/create"
                    }
                ],
                "title": "CREATE"
            },
            "db__mysql_cursors": {},
            "db__mysql_ddl_dml_et_al": {},
            "db__mysql_delete_instructie": {},
            "db__mysql_distinct": {},
            "db__mysql_drop_instructie": {},
            "db__mysql_een_op_een": {},
            "db__mysql_een_op_veel": {},
            "db__mysql_enum": {},
            "db__mysql_erd_design": {},
            "db__mysql_error_handling": {},
            "db__mysql_functies": {},
            "db__mysql_generated_column": {},
            "db__mysql_group_by": {},
            "db__mysql_having": {},
            "db__mysql_insert_instructie": {
                "URLs": [
                    {
                        "description": "Uitleg (tekst)",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/deeltalen/dml/insert"
                    }
                ],
                "title": "INSERT"
            },
            "db__mysql_join_een_op_een_of_op_veel": {},
            "db__mysql_join_veel_op_veel": {},
            "db__mysql_left_join": {},
            "db__mysql_like_operator": {},
            "db__mysql_limit": {},
            "db__mysql_limit_offset": {},
            "db__mysql_null": {},
            "db__mysql_object_access_control": {},
            "db__mysql_order_asc_desc": {},
            "db__mysql_primaire_sleutels": {
                "URLs": [
                    {
                        "description": "primaire sleutel toevoegen aan / verwijderen uit een bestaande tabel",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/deeltalen/ddl/primaire-sleutel-toevoegen-verwijderen"
                    },
                    {
                        "description": "primaire sleutel toevoegen aan een nieuwe tabel",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/deeltalen/ddl/tabel-aanmaken-met-sleutel"
                    }
                ]
            },
            "db__mysql_select_basis": {
                "title": "SELECT [FROM ...] [WHERE ...] [ORDER BY ...]"
            },
            "db__mysql_select_into": {},
            "db__mysql_sessievariabelen": {},
            "db__mysql_signal": {},
            "db__mysql_sleutels": {},
            "db__mysql_stored_function": {},
            "db__mysql_stored_procedure_syntax_en_vars": {},
            "db__mysql_stored_programs": {},
            "db__mysql_subqueries": {},
            "db__mysql_temp_tables": {},
            "db__mysql_transacties": {},
            "db__mysql_trigger": {},
            "db__mysql_union_union_all": {},
            "db__mysql_unique_constraint": {},
            "db__mysql_updatable_views": {},
            "db__mysql_update_instructie": {},
            "db__mysql_update_join": {},
            "db__mysql_veel_op_veel": {},
            "db__mysql_vergelijkingen": {},
            "db__mysql_views": {},
            "db__mysql_vreemde_sleutels": {
                "URLs": [
                    {
                        "description": "uitleg vreemde sleutels aanmaken / verwijderen",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/deeltalen/ddl/vreemde-sleutel"
                    }
                ]
            },
            "db__wat_is_een_databank": {
                "URLs": [
                    {
                        "description": "Inleiding (tekst)",
                        "url": "http://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/databanken/inleiding"
                    },
                    {
                        "description": "Inleiding (filmpje)",
                        "url": "https://youtu.be/3J-dNJzip2Q"
                    },
                    {
                        "description": "Voorbeeld",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/databanken/voorbeeld"
                    }
                ],
                "title": "Wat is een databank?"
            },
            "db__wat_is_een_relationele_database": {
                "URLs": [
                    {
                        "description": "Uitleg (tekst)",
                        "url": "https://apwt.gitbook.io/cursus-databanken/semester-1-databanken-intro/databanken/wat-is-een-relationele-databank"
                    },
                    {
                        "description": "Uitleg (filmpje)",
                        "url": "https://youtu.be/wKcjQbXl-g4"
                    }
                ],
                "title": "Wat is een relationele database?"
            },
            "express__authenticatie_en_autorisatie": {},
            "express__basis": {},
            "express__body_parser": {},
            "express__cookies": {},
            "express__cors": {},
            "express__ejs": {},
            "express__ejs_advanced": {},
            "express__env_variabelen": {},
            "express__jwt_algemeen": {},
            "express__jwt_npm_package": {},
            "express__jwt_oefening": {},
            "express__mysql_connector": {},
            "express__oauth": {},
            "express__post_requests": {},
            "express__static_files": {},
            "express__wat_is_een_backend_framework": {},
            "friendface__adding_friendships": {},
            "friendface__completing_the_app": {},
            "friendface__gitlab": {},
            "friendface__implementation_adding_clubs": {},
            "friendface__implementation_admin_page_data_management": {},
            "friendface__implementation_barebones_user_profiles": {},
            "friendface__implementation_data_integrity": {},
            "friendface__implementation_deleting_profile": {},
            "friendface__implementation_editing_data": {},
            "friendface__implementation_env_secrets": {},
            "friendface__implementation_experimentation": {},
            "friendface__implementation_fixing_data_management": {},
            "friendface__implementation_generalizing_sneezes": {},
            "friendface__implementation_mirrored_friendships": {},
            "friendface__implementation_mock_sneezes": {},
            "friendface__implementation_mysql_scripts_startup": {},
            "friendface__implementation_paginated_timeline": {},
            "friendface__implementation_posting_sneezes": {},
            "friendface__implementation_profile_pictures": {},
            "friendface__implementation_random_friendships": {},
            "friendface__implementation_random_memberships": {},
            "friendface__implementation_random_reactions": {},
            "friendface__implementation_reactions": {},
            "friendface__implementation_real_login": {},
            "friendface__implementation_showing_all_sneezes": {},
            "friendface__implementation_showing_friends_sneezes": {},
            "friendface__implementation_showing_memberships": {},
            "friendface__implementation_simple_admin_page": {},
            "friendface__implementation_specific_user_login_page": {},
            "friendface__implementation_speedup": {},
            "friendface__implementation_statistics": {},
            "friendface__intent_adding_clubs": {},
            "friendface__intent_admin_page_data_management": {},
            "friendface__intent_barebones_user_profiles": {},
            "friendface__intent_data_integrity": {},
            "friendface__intent_deleting_profile": {},
            "friendface__intent_editing_data": {},
            "friendface__intent_env_secrets": {},
            "friendface__intent_experimentation": {},
            "friendface__intent_fixing_data_management": {},
            "friendface__intent_generalizing_sneezes": {},
            "friendface__intent_mirrored_friendships": {},
            "friendface__intent_mock_sneezes": {},
            "friendface__intent_mysql_scripts_startup": {},
            "friendface__intent_paginated_timeline": {},
            "friendface__intent_posting_sneezes": {},
            "friendface__intent_profile_pictures": {},
            "friendface__intent_random_friendships": {},
            "friendface__intent_random_memberships": {},
            "friendface__intent_random_reactions": {},
            "friendface__intent_reactions": {},
            "friendface__intent_real_login": {},
            "friendface__intent_showing_all_sneezes": {},
            "friendface__intent_showing_friends_sneezes": {},
            "friendface__intent_showing_memberships": {},
            "friendface__intent_simple_admin_page": {},
            "friendface__intent_specific_user_login_page": {},
            "friendface__intent_speedup": {},
            "friendface__intent_statistics": {},
            "friendface__introduction": {},
            "friendface__setup_work_environment_implementation_express": {},
            "friendface__setup_work_environment_implementation_stack_with_mysql": {},
            "friendface__setup_work_environment_implementation_version_control": {},
            "friendface__setup_work_environment_intent_express": {},
            "friendface__setup_work_environment_intent_stack_with_mysql": {},
            "friendface__setup_work_environment_intent_version_control": {},
            "git__git_add": {
                "title": "git add"
            },
            "git__git_basisbegrippen": {
                "title": "Basisbegrippen Git"
            },
            "git__git_branch": {
                "title": "branches"
            },
            "git__git_checkout": {
                "title": "git checkout"
            },
            "git__git_clone": {
                "title": "git clone"
            },
            "git__git_commit": {
                "title": "git commit"
            },
            "git__git_init": {
                "title": "git init"
            },
            "git__git_log": {
                "title": "git log"
            },
            "git__git_merge": {
                "title": "git merge"
            },
            "git__git_pull": {
                "title": "git pull"
            },
            "git__git_push": {
                "title": "git push"
            },
            "git__git_remote": {
                "title": "Remotes"
            },
            "git__git_rm": {
                "title": "git rm"
            },
            "git__git_status": {
                "title": "git status"
            },
            "git__gitignore": {
                "title": "gitignore"
            },
            "git__installatie_prerequisites": {
                "title": "Installatie vereisten Git"
            },
            "git__wat_is_lokaal_versiebeheer": {
                "title": "Wat is lokaal versiebeheer?"
            },
            "git__wat_is_versiebeheer": {
                "title": "Wat is versiebeheer?"
            },
            "operationeel__absoluut_vs_relatief": {
                "URLs": [
                    {
                        "description": "tekst Gitbook",
                        "url": "https://apwt.gitbook.io/g_pro-module-2-backend-development/"
                    }
                ]
            },
            "operationeel__compose_file": {},
            "operationeel__containers_starten_docker": {
                "title": "Containers starten in Docker"
            },
            "operationeel__env_variabelen": {},
            "operationeel__hashing": {},
            "operationeel__installatie_docker": {
                "URLs": [
                    {
                        "description": "activering WSL2 (enkel tot 5:15, enkel voor Windowsgebruikers)",
                        "url": "https://youtu.be/X3bPWl9Z2D0"
                    },
                    {
                        "description": "installatie Docker op Windows (enkel tot 9:25, enkel voor Windowsgebruikers)",
                        "url": "https://www.youtube.com/watch?v=h0Lwtcje-Jo"
                    },
                    {
                        "description": "installatie Docker op MacOS (enkel tot 4:42)",
                        "url": "https://youtu.be/gcacQ29AjOo"
                    }
                ],
                "assignments": [
                    {
                        "description": "Testen installatie Docker",
                        "path": "oefeningen/Docker/hello-world-in-container.md"
                    }
                ],
                "title": "Installatie Docker"
            },
            "operationeel__installatie_docker_compose": {
                "title": "Installatie Docker Compose"
            },
            "operationeel__letsencrypt": {},
            "operationeel__reproduceerbare_omgevingen": {},
            "operationeel__reproduceerbare_stacks": {},
            "operationeel__rest_api": {},
            "operationeel__salting": {},
            "operationeel__ssh_config": {},
            "operationeel__ssh_file_transfer": {},
            "operationeel__ssh_remote_login": {},
            "operationeel__ssh_sleutels_genereren": {},
            "operationeel__ssh_werking": {},
            "operationeel__stack_starten": {},
            "operationeel__thunder_client": {
                "URLs": [
                    {
                        "description": "tekst Gitbook",
                        "url": "https://apwt.gitbook.io/g_pro-module-2-backend-development/"
                    }
                ]
            },
            "operationeel__vscode_remote_dev_compose": {},
            "operationeel__vscode_remote_dev_post_start_command": {},
            "operationeel__vscode_remote_dev_single_container": {},
            "operationeel__wat_is_docker": {
                "title": "Wat is Docker?"
            },
            "operationeel__wat_is_docker_compose": {
                "title": "Wat is Docker Compose?"
            }
        },
        "requires_all_of": {
            "db__basisstructuren_relationele_database": [
                "db__wat_is_een_relationele_database"
            ],
            "db__essentiele_datatypes_mysql": [
                "db__inleiding_tot_mysql_taal"
            ],
            "db__export": [
                "db__mysql_insert_instructie"
            ],
            "db__hash_functies": [
                "db__mysql_functies",
                "operationeel__hashing"
            ],
            "db__inleiding_tot_mysql_taal": [
                "db__installatie_mysql_workbench",
                "db__basisstructuren_relationele_database"
            ],
            "db__installatie_mysql_via_docker": [
                "db__wat_is_een_relationele_database",
                "operationeel__containers_starten_docker"
            ],
            "db__installatie_mysql_workbench": [
                "db__installatie_mysql_via_docker"
            ],
            "db__mysql_aggregatiefuncties": [
                "db__mysql_functies"
            ],
            "db__mysql_alle_clausules_samen": [
                "db__mysql_order_asc_desc",
                "db__mysql_having"
            ],
            "db__mysql_alle_joins": [
                "db__mysql_left_join"
            ],
            "db__mysql_alter_instructie": [
                "db__mysql_create_instructie"
            ],
            "db__mysql_booleaanse_operatoren": [
                "db__mysql_update_instructie"
            ],
            "db__mysql_cascade": [
                "db__mysql_delete_instructie",
                "db__mysql_een_op_veel"
            ],
            "db__mysql_control_flow": [
                "db__mysql_stored_procedure_syntax_en_vars"
            ],
            "db__mysql_create_instructie": [
                "db__essentiele_datatypes_mysql",
                "db__mysql_ddl_dml_et_al"
            ],
            "db__mysql_cursors": [
                "db__mysql_error_handling"
            ],
            "db__mysql_ddl_dml_et_al": [],
            "db__mysql_delete_instructie": [
                "db__mysql_select_basis"
            ],
            "db__mysql_distinct": [
                "db__mysql_aggregatiefuncties"
            ],
            "db__mysql_drop_instructie": [
                "db__mysql_create_instructie"
            ],
            "db__mysql_een_op_een": [
                "db__mysql_vreemde_sleutels"
            ],
            "db__mysql_een_op_veel": [
                "db__mysql_een_op_een"
            ],
            "db__mysql_enum": [
                "db__mysql_create_instructie"
            ],
            "db__mysql_erd_design": [
                "db__mysql_veel_op_veel"
            ],
            "db__mysql_error_handling": [
                "db__mysql_control_flow"
            ],
            "db__mysql_functies": [],
            "db__mysql_generated_column": [],
            "db__mysql_group_by": [
                "db__mysql_aggregatiefuncties"
            ],
            "db__mysql_having": [
                "db__mysql_group_by"
            ],
            "db__mysql_insert_instructie": [
                "db__mysql_create_instructie"
            ],
            "db__mysql_join_een_op_een_of_op_veel": [
                "db__mysql_een_op_veel"
            ],
            "db__mysql_join_veel_op_veel": [
                "db__mysql_veel_op_veel",
                "db__mysql_join_een_op_een_of_op_veel"
            ],
            "db__mysql_left_join": [
                "db__mysql_veel_op_veel"
            ],
            "db__mysql_like_operator": [
                "db__mysql_update_instructie"
            ],
            "db__mysql_limit": [
                "db__mysql_select_basis"
            ],
            "db__mysql_limit_offset": [
                "db__mysql_limit"
            ],
            "db__mysql_null": [
                "db__mysql_update_instructie"
            ],
            "db__mysql_object_access_control": [
                "db__mysql_stored_procedure_syntax_en_vars"
            ],
            "db__mysql_order_asc_desc": [
                "db__mysql_select_basis"
            ],
            "db__mysql_primaire_sleutels": [
                "db__mysql_sleutels"
            ],
            "db__mysql_select_basis": [
                "db__mysql_insert_instructie"
            ],
            "db__mysql_select_into": [
                "db__mysql_stored_procedure_syntax_en_vars"
            ],
            "db__mysql_sessievariabelen": [
                "db__mysql_aggregatiefuncties"
            ],
            "db__mysql_signal": [],
            "db__mysql_sleutels": [
                "db__mysql_select_basis"
            ],
            "db__mysql_stored_function": [
                "db__mysql_stored_programs"
            ],
            "db__mysql_stored_procedure_syntax_en_vars": [
                "db__mysql_stored_programs"
            ],
            "db__mysql_stored_programs": [
                "db__mysql_views"
            ],
            "db__mysql_subqueries": [
                "db__mysql_sessievariabelen"
            ],
            "db__mysql_temp_tables": [],
            "db__mysql_transacties": [
                "db__mysql_error_handling"
            ],
            "db__mysql_trigger": [],
            "db__mysql_union_union_all": [
                "db__mysql_select_basis"
            ],
            "db__mysql_unique_constraint": [],
            "db__mysql_updatable_views": [
                "db__mysql_views"
            ],
            "db__mysql_update_instructie": [
                "db__mysql_select_basis"
            ],
            "db__mysql_update_join": [
                "db__mysql_join_een_op_een_of_op_veel"
            ],
            "db__mysql_veel_op_veel": [
                "db__mysql_een_op_veel"
            ],
            "db__mysql_vergelijkingen": [
                "db__mysql_booleaanse_operatoren"
            ],
            "db__mysql_views": [],
            "db__mysql_vreemde_sleutels": [
                "db__mysql_primaire_sleutels"
            ],
            "db__wat_is_een_databank": [],
            "db__wat_is_een_relationele_database": [
                "db__wat_is_een_databank"
            ],
            "express__authenticatie_en_autorisatie": [],
            "express__basis": [
                "express__wat_is_een_backend_framework",
                "operationeel__absoluut_vs_relatief"
            ],
            "express__body_parser": [],
            "express__cookies": [],
            "express__cors": [],
            "express__ejs": [
                "express__basis"
            ],
            "express__ejs_advanced": [],
            "express__env_variabelen": [],
            "express__jwt_algemeen": [],
            "express__jwt_npm_package": [
                "express__jwt_algemeen"
            ],
            "express__jwt_oefening": [],
            "express__mysql_connector": [],
            "express__oauth": [],
            "express__post_requests": [],
            "express__static_files": [],
            "express__wat_is_een_backend_framework": [],
            "friendface__adding_friendships": [],
            "friendface__completing_the_app": [],
            "friendface__gitlab": [],
            "friendface__implementation_adding_clubs": [
                "db__mysql_veel_op_veel"
            ],
            "friendface__implementation_admin_page_data_management": [
                "db__mysql_update_instructie",
                "db__mysql_delete_instructie",
                "express__post_requests",
                "friendface__gitlab"
            ],
            "friendface__implementation_barebones_user_profiles": [
                "db__mysql_insert_instructie"
            ],
            "friendface__implementation_data_integrity": [
                "db__mysql_signal",
                "db__mysql_trigger"
            ],
            "friendface__implementation_deleting_profile": [
                "db__mysql_transacties"
            ],
            "friendface__implementation_editing_data": [
                "express__cors"
            ],
            "friendface__implementation_env_secrets": [
                "operationeel__env_variabelen"
            ],
            "friendface__implementation_experimentation": [
                "git__git_checkout"
            ],
            "friendface__implementation_fixing_data_management": [
                "db__mysql_cascade"
            ],
            "friendface__implementation_generalizing_sneezes": [
                "db__mysql_alter_instructie",
                "db__mysql_null"
            ],
            "friendface__implementation_mirrored_friendships": [
                "db__mysql_unique_constraint",
                "db__mysql_generated_column",
                "db__mysql_union_union_all",
                "db__mysql_stored_function"
            ],
            "friendface__implementation_mock_sneezes": [
                "db__mysql_limit",
                "db__mysql_control_flow",
                "db__mysql_select_into"
            ],
            "friendface__implementation_mysql_scripts_startup": [],
            "friendface__implementation_paginated_timeline": [
                "db__mysql_limit_offset"
            ],
            "friendface__implementation_posting_sneezes": [
                "db__mysql_een_op_veel"
            ],
            "friendface__implementation_profile_pictures": [
                "express__static_files"
            ],
            "friendface__implementation_random_friendships": [],
            "friendface__implementation_random_memberships": [
                "db__mysql_cursors"
            ],
            "friendface__implementation_random_reactions": [
                "db__mysql_subqueries"
            ],
            "friendface__implementation_reactions": [
                "db__mysql_enum"
            ],
            "friendface__implementation_real_login": [
                "db__hash_functies",
                "operationeel__salting"
            ],
            "friendface__implementation_showing_all_sneezes": [
                "db__mysql_join_een_op_een_of_op_veel"
            ],
            "friendface__implementation_showing_friends_sneezes": [
                "db__mysql_stored_procedure_syntax_en_vars"
            ],
            "friendface__implementation_showing_memberships": [
                "db__mysql_join_veel_op_veel"
            ],
            "friendface__implementation_simple_admin_page": [
                "db__mysql_order_asc_desc",
                "express__ejs",
                "express__mysql_connector"
            ],
            "friendface__implementation_specific_user_login_page": [
                "express__cookies",
                "express__jwt_npm_package"
            ],
            "friendface__implementation_speedup": [
                "db__export"
            ],
            "friendface__implementation_statistics": [
                "db__mysql_like_operator",
                "db__mysql_alle_clausules_samen"
            ],
            "friendface__intent_adding_clubs": [],
            "friendface__intent_admin_page_data_management": [],
            "friendface__intent_barebones_user_profiles": [],
            "friendface__intent_data_integrity": [],
            "friendface__intent_deleting_profile": [],
            "friendface__intent_editing_data": [],
            "friendface__intent_env_secrets": [],
            "friendface__intent_experimentation": [],
            "friendface__intent_fixing_data_management": [],
            "friendface__intent_generalizing_sneezes": [],
            "friendface__intent_mirrored_friendships": [],
            "friendface__intent_mock_sneezes": [],
            "friendface__intent_mysql_scripts_startup": [],
            "friendface__intent_paginated_timeline": [],
            "friendface__intent_posting_sneezes": [],
            "friendface__intent_profile_pictures": [],
            "friendface__intent_random_friendships": [],
            "friendface__intent_random_memberships": [],
            "friendface__intent_random_reactions": [],
            "friendface__intent_reactions": [],
            "friendface__intent_real_login": [],
            "friendface__intent_showing_all_sneezes": [],
            "friendface__intent_showing_friends_sneezes": [],
            "friendface__intent_showing_memberships": [],
            "friendface__intent_simple_admin_page": [],
            "friendface__intent_specific_user_login_page": [],
            "friendface__intent_speedup": [],
            "friendface__intent_statistics": [],
            "friendface__introduction": [],
            "friendface__setup_work_environment_implementation_express": [
                "express__basis",
                "operationeel__vscode_remote_dev_post_start_command"
            ],
            "friendface__setup_work_environment_implementation_stack_with_mysql": [
                "db__installatie_mysql_workbench",
                "operationeel__vscode_remote_dev_compose"
            ],
            "friendface__setup_work_environment_implementation_version_control": [
                "git__gitignore",
                "git__git_push"
            ],
            "friendface__setup_work_environment_intent_express": [
                "express__wat_is_een_backend_framework"
            ],
            "friendface__setup_work_environment_intent_stack_with_mysql": [],
            "friendface__setup_work_environment_intent_version_control": [],
            "git__git_add": [
                "git__git_basisbegrippen"
            ],
            "git__git_basisbegrippen": [
                "git__git_init"
            ],
            "git__git_branch": [
                "git__git_log",
                "git__git_pull"
            ],
            "git__git_checkout": [
                "git__git_branch"
            ],
            "git__git_clone": [
                "git__git_remote"
            ],
            "git__git_commit": [
                "git__git_add"
            ],
            "git__git_init": [
                "git__wat_is_lokaal_versiebeheer"
            ],
            "git__git_log": [
                "git__git_commit"
            ],
            "git__git_merge": [
                "git__git_branch"
            ],
            "git__git_pull": [
                "git__git_push"
            ],
            "git__git_push": [
                "git__git_remote"
            ],
            "git__git_remote": [
                "git__git_commit"
            ],
            "git__git_rm": [
                "git__git_status"
            ],
            "git__git_status": [
                "git__gitignore"
            ],
            "git__gitignore": [
                "git__git_commit"
            ],
            "git__installatie_prerequisites": [
                "git__wat_is_versiebeheer"
            ],
            "git__wat_is_lokaal_versiebeheer": [
                "git__installatie_prerequisites"
            ],
            "git__wat_is_versiebeheer": [],
            "operationeel__absoluut_vs_relatief": [],
            "operationeel__compose_file": [
                "operationeel__containers_starten_docker",
                "operationeel__installatie_docker_compose",
                "operationeel__wat_is_docker_compose"
            ],
            "operationeel__containers_starten_docker": [
                "operationeel__installatie_docker"
            ],
            "operationeel__env_variabelen": [
                "git__gitignore"
            ],
            "operationeel__hashing": [],
            "operationeel__installatie_docker": [
                "operationeel__wat_is_docker"
            ],
            "operationeel__installatie_docker_compose": [
                "operationeel__installatie_docker"
            ],
            "operationeel__letsencrypt": [],
            "operationeel__reproduceerbare_omgevingen": [],
            "operationeel__reproduceerbare_stacks": [],
            "operationeel__rest_api": [],
            "operationeel__salting": [],
            "operationeel__ssh_config": [
                "operationeel__ssh_sleutels_genereren"
            ],
            "operationeel__ssh_file_transfer": [
                "operationeel__ssh_config"
            ],
            "operationeel__ssh_remote_login": [
                "operationeel__ssh_config"
            ],
            "operationeel__ssh_sleutels_genereren": [
                "operationeel__ssh_werking"
            ],
            "operationeel__ssh_werking": [],
            "operationeel__stack_starten": [
                "operationeel__compose_file"
            ],
            "operationeel__thunder_client": [
                "operationeel__rest_api"
            ],
            "operationeel__vscode_remote_dev_compose": [
                "operationeel__stack_starten",
                "operationeel__vscode_remote_dev_single_container"
            ],
            "operationeel__vscode_remote_dev_post_start_command": [
                "operationeel__vscode_remote_dev_single_container"
            ],
            "operationeel__vscode_remote_dev_single_container": [
                "operationeel__containers_starten_docker"
            ],
            "operationeel__wat_is_docker": [],
            "operationeel__wat_is_docker_compose": []
        }
    }',
    true
);

echo $OUTPUT->header();

// contains info per topic
$course_module_ids = array();
$nodes_to_pullees = array();
$nodes_to_pushers = array();
foreach ($structure['nodes'] as $index => $node) {
    // TODO: first, get everything to show up again with new structure
    // need to get all aspects working again, one by one, including attachments
    if (!array_key_exists($node['id'], $course_module_ids)) {
        $course_module_ids[$node['id']] = array('assignments' => []);
    }
    $pullees = $structure['requires_all_of'][$node['id']];
    $pushers = $structure['motivated_by_any_of'][$node['id']];
    $nodes_to_pullees[$node['id']] = $pullees;
    $nodes_to_pushers[$node['id']] = $pushers;
    $course_module_ids = create_course_topic($DB, $course, $node, $course_module_ids, $index);
}
foreach ($structure['nodes'] as $index => $node) {
    // TODO: modify availability based on nodes_to_pullees (conjunction of all of those) and nodes_to_pushers (disjunction of all of those)
}


echo html_writer::start_tag('p');
echo get_string('create_failed', 'local_distance');
echo html_writer::end_tag('p');

echo $OUTPUT->footer();
