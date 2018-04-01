<?php

/*
    Plugin Name: Tunnelbanejakten
    Description: Made for Tunnelbanejakten.se
    Version: 1.1.0
    Author: Mattias Forsman and Mikael Svensson
    Author URI: https://tunnelbanejakten.se
*/

include_once 'db.init.php';
include_once 'view.start.php';
include_once 'view.team.php';
include_once 'view.competition.php';
include_once 'view.summary.php';
include_once 'view.answers.php';
include_once 'view.sendmessage.php';
include_once 'view.users.php';

const QUESTION_GRADING_TYPE_IGNORE = "ignore";
const QUESTION_GRADING_TYPE_SUBMITTED_ANSWER_IS_POINTS = "submitted_answer_is_points";
const QUESTION_GRADING_TYPE_ONE_OF = "one_of";
const QUESTION_GRADING_TYPE_ALL_OF = "all_of";

const SLUG = 'tsl';
const FILE = __FILE__;
const PATH = __DIR__;

define('SAVEQUERIES', true);

$defaultValidator = function ($userAnswer, $correctAnswers) {
    return !empty($userAnswer) && in_array(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $userAnswer)), array_map(function ($value) {
            return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $value));
        }, $correctAnswers));
};

add_action('admin_menu', 'tsl_add_menu');

add_action('plugins_loaded', 'tsl_db_migrate');

register_activation_hook( __FILE__, 'tsl_db_migrate' );

function tsl_add_menu()
{
    add_menu_page('Tunnelbanejakten', 'Tunnelbanejakten', 'manage_options', SLUG, 'tsl_show_page', plugins_url('tsl/TSL-Ikon-Gray-20x20.png'));
}

function tsl_show_page()
{
//    global $wpdb;
    $selected_team = $_GET['tsl_team'];
    $selected_competition = $_GET['tsl_competition'];
    if ($_GET['tsl_report'] == 'points') {
        tsl_print_overview_report($selected_competition);
    } elseif ($_GET['tsl_report'] == 'nextgen') {
        tsl_print_nextgen_report($selected_competition, $selected_team);
    } elseif ($_GET['tsl_report'] == 'competition') {
        tsl_print_competition_page($selected_competition);
    } elseif ($_GET['tsl_report'] == 'answers') {
        tsl_print_answers_form($selected_competition);
    } elseif ($_GET['tsl_report'] == 'sendmessage') {
        tsl_send_messages_form($selected_competition);
    } elseif ($_GET['tsl_report'] == 'users') {
        tsl_users($selected_competition);
    } else {
        if (empty($selected_team)) {
            tsl_print_start_page();
        } else {
            tsl_print_responses($selected_team);
        }
    }
//    $all_queries = array_map(function ($o) {
//        return preg_replace('/\s+/', ' ', $o[0]);
//    }, $wpdb->queries);
//    print '<pre>';
//    print_r(array_count_values($all_queries));
//    print_r($all_queries);
//    print '</pre>';
}

function tsl_get_competition_answers($competition_forms_key_prefix)
{
    global $wpdb;

    $query = $wpdb->prepare(SQL_COMPETITION_ANSWERS,
        "tsl-$competition_forms_key_prefix-%",
        "tsl-$competition_forms_key_prefix-%");

    $results = $wpdb->get_results($query);
    if ($results) {
        $answers = [];
        foreach ($results as $result) {
            $answers[$result->frm_field_key] = array(
                'type' => $result->grading_policy,
                'params' => explode(';', $result->grading_policy_parameter)
            );
            if ($result->points > 0) {
                $answers[$result->frm_field_key]['points'] = $result->points;
            }
        }
        return $answers;
    }

    return array();
}

function tsl_update_competition_answers($competition_name, $answers)
{
    global $wpdb;

    foreach ($answers as $question_key => $answer_config) {
        $answer_type = $answer_config['type'];
        $answer_params = !empty($answer_config['params']) ? join(';', $answer_config['params']) : '';
        $answer_points = $answer_config['points'];
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO " . $wpdb->prefix . "tsl_answers
                (frm_field_key, grading_policy, grading_policy_parameter, points)
                VALUES
                  (%s, %s, %s, %f)
                ON DUPLICATE KEY UPDATE
                  grading_policy           = values(grading_policy),
                  grading_policy_parameter = values(grading_policy_parameter),
                  points                   = values(points)",
                $question_key,
                $answer_type,
                $answer_params,
                $answer_points
            )
        );
    }
}

function tsl_set_checked($competition_name, $team_name)
{
    global $wpdb;

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO " . $wpdb->prefix . "tsl_check_timestamps
                (competition_key, team_name, checked_at)
                VALUES
                  (%s, %s, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                  checked_at           = CURRENT_TIMESTAMP",
            $competition_name,
            $team_name
        )
    );

}

function tsl_get_checked($competition_name, $team_name)
{
    global $wpdb;
    $query = $wpdb->prepare('SELECT checked_at FROM wp_tsl_check_timestamps WHERE competition_key = %s AND team_name = %s',
        $competition_name,
        $team_name);
    $results = $wpdb->get_results($query);
    return !empty($results) ? $results[0]->checked_at : null;
}

function tsl_set_points_override($entry_id, $question_key, $points)
{
    global $wpdb;
    if (!empty($entry_id) && !empty($question_key)) {
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO " . $wpdb->prefix . "tsl_overrides
                      (frm_items_id, frm_field_key, points)
                    VALUES
                      (%d, %s, %f)
                    ON DUPLICATE KEY UPDATE
                      points                   = values(points)",
                $entry_id,
                $question_key,
                $points
            )
        );
    }
}

function tsl_unset_points_override($entry_id, $question_key)
{
    global $wpdb;
    if (!empty($entry_id) && !empty($question_key)) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . $wpdb->prefix . "tsl_overrides WHERE frm_items_id = %d AND frm_field_key = %s",
                $entry_id,
                $question_key
            )
        );
    }
}

function tsl_sql_questions_and_team_answers($team_form_field_key_prefix = 'team', $competition_forms_key_prefix = 'tsl18', $team_name)
{
    global $wpdb;
    $query = $wpdb->prepare(SQL_QUESTIONS_AND_TEAM_ANSWERS,
        "$team_form_field_key_prefix%",
        $team_name,
        "tsl-$competition_forms_key_prefix-%",
        "tsl-$competition_forms_key_prefix-%",
        "$team_form_field_key_prefix%");
    $results = $wpdb->get_results($query);
    return $results;
}

function tsl_get_competitions_forms_and_question_count($team_form_field_key_prefix = 'team', $competition_forms_key_prefix = 'tsl18')
{
    global $wpdb;
    $query = $wpdb->prepare(SQL_TSL_COMPETITION_FORMS_AND_QUESTION_COUNT,
        "tsl-$competition_forms_key_prefix-%",
        "tsl-$competition_forms_key_prefix-%",
        "$team_form_field_key_prefix%");
    $results = $wpdb->get_results($query);
    return $results;

}

function tsl_get_answers_per_section_and_team($competition_forms_key_prefix = 'tsl18')
{
    global $wpdb;
    $wpdb->show_errors(true);
    $query = $wpdb->prepare(SQL_ANSWERS_PER_SECTION_AND_TEAM,
        "team%",
        "tsl-$competition_forms_key_prefix-%",
        "tsl-$competition_forms_key_prefix-%",
        "team%");

    $results = $wpdb->get_results($query);

    return $results;
}

function tsl_get_competition_teams($competition_forms_key_prefix = 'tsl18')
{
    global $wpdb;
    $query = $wpdb->prepare(SQL_TEAM_LIST,
        "tsl-$competition_forms_key_prefix-%",
        "tsl-$competition_forms_key_prefix-%",
        'team_name%',
        'age_group%');

    $results = $wpdb->get_results($query);
    return $results;

}

$answer_cache = [];

function tsl_grades_automated($competition_name, $team_name)
{
    global $answer_cache;
    $response = array();
    if (!isset($answer_cache[$competition_name])) {
        $answer_cache[$competition_name] = tsl_get_competition_answers($competition_name);
    }
    $correct_answers = $answer_cache[$competition_name];
    $questions_and_submitted_answers = tsl_sql_questions_and_team_answers("team", $competition_name, $team_name);
    foreach ($questions_and_submitted_answers as $question_and_submitted_answer) {
        $question_key = $question_and_submitted_answer->question_key;
        if (isset($question_and_submitted_answer->override_points) && $question_and_submitted_answer->override_points != null) {
            $response[$question_key] = floatval($question_and_submitted_answer->override_points);
            continue;
        }
        if (isset($correct_answers[$question_key])) {
            $correct_answer = $correct_answers[$question_key];

            $submitted_answer = unserialize($question_and_submitted_answer->submitted_answer);
            if ($submitted_answer === false) {
                $submitted_answer = $question_and_submitted_answer->submitted_answer;
            }
            if (!is_array($submitted_answer)) {
                $submitted_answer = array($submitted_answer);
            }
            $submitted_answer = array_map(function ($v) {
                return strtolower(trim($v));
            }, $submitted_answer);
            if (is_array($correct_answer['params'])) {
                $correct_answer_params = array_map(function ($v) {
                    return strtolower(trim($v));
                }, $correct_answer['params']);
            } else {
                $correct_answer_params = array();
            }
            switch ($correct_answer['type']) {
                case QUESTION_GRADING_TYPE_SUBMITTED_ANSWER_IS_POINTS:
                    $response[$question_key] = floatval($submitted_answer[0]);
                    break;
                case QUESTION_GRADING_TYPE_IGNORE:
                    break;
                case QUESTION_GRADING_TYPE_ONE_OF:
                    if (in_array($submitted_answer[0], $correct_answer_params)) {
                        $response[$question_key] = $correct_answer['points'];
                    } else {
                        $response[$question_key] = 0;
                    }
                    break;
                case QUESTION_GRADING_TYPE_ALL_OF:
                    $missing_answers = array_diff($correct_answer_params, $submitted_answer);
                    if (count($missing_answers) == 0) {
                        $response[$question_key] = $correct_answer['points'];
                    } else {
                        $response[$question_key] = 0;
                    }
                    break;
                default:
                    print("Cannot handle " . $correct_answer['type']);
                    break;
            }
        }
    }
    return $response;
}

function tsl_grade_final($competition_name, $team_name)
{
    $auto_points = tsl_grades_automated($competition_name, $team_name);
    return array_sum(array_values($auto_points));
}

function tsl_get_competition_team_contacts($competition_forms_key_prefix = 'tsl18')
{
    global $wpdb;
    $query = $wpdb->prepare(SQL_TEAM_CONTACTS,
        "tsl-$competition_forms_key_prefix-%",
        'team_name%',
        'age_group%',
        'phone_primary%',
        'phone_secondary%');

//    print_r($query);

    $results = $wpdb->get_results($query);
    return $results;

}

function tsl_derive_password($username)
{
    return substr(md5($username), 0, 4);
}