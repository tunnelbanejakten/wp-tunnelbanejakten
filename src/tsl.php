<?php

/*
    Plugin Name: TSL
    Description: Made for Tunnelbanejakten.se
    Version: 1.0.0
    Author: Mattias Forsman and Mikael Svensson
    Author URI: https://tunnelbanejakten.se
*/

include_once 'view.start.php';
include_once 'view.team.php';
include_once 'view.competition.php';
include_once 'view.summary.php';
include_once 'view.answers.php';

const QUESTION_GRADING_TYPE_IGNORE = "ignore";
const QUESTION_GRADING_TYPE_SUBMITTED_ANSWER_IS_POINTS = "submitted_answer_is_points";
const QUESTION_GRADING_TYPE_ONE_OF = "one_of";
const QUESTION_GRADING_TYPE_ALL_OF = "all_of";

const SLUG = 'tsl';
const FILE = __FILE__;
const PATH = __DIR__;

$defaultValidator = function ($userAnswer, $correctAnswers) {
    return !empty($userAnswer) && in_array(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $userAnswer)), array_map(function ($value) {
            return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $value));
        }, $correctAnswers));
};

add_action('admin_menu', 'tsl_add_menu');

function tsl_add_menu()
{
    add_menu_page('Tunnelbanejakten', 'Tunnelbanejakten', 'manage_options', SLUG, 'tsl_show_page', plugins_url('tsl/TSL-Ikon-Gray-20x20.png'));
}

function tsl_show_page()
{
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
    } else {
        if (empty($selected_team)) {
            tsl_print_start_page();
        } else {
            tsl_print_responses($selected_team);
        }
    }
}

function tsl_get_competition_answers($competition_name)
{
    $all_answers = get_option("tsl_answers");

    if (is_array($all_answers) && isset($all_answers[$competition_name])) {
        return $all_answers[$competition_name];
    }
    return array();
}

function tsl_update_competition_answers($competition_name, $answers)
{
    $all_answers = get_option("tsl_answers");

    if (!is_array($all_answers)) {
        $all_answers = array();
        add_option("tsl_answers", $all_answers, null, 'no');
    }

    $all_answers[$competition_name] = $answers;

    update_option("tsl_answers", $all_answers);
}

function tsl_get_grades_overrides($team_name)
{
    $all_overrides = get_option("tsl_points_overrides");

    if (is_array($all_overrides) && isset($all_overrides[$team_name])) {
        return $all_overrides[$team_name];
    }
    return array();
}

function tsl_update_grades_overrides($team_name, $points)
{
    $all_overrides = get_option("tsl_points_overrides");

    if (!is_array($all_overrides)) {
        $all_overrides = array();
        add_option("tsl_points_overrides", $all_overrides, null, 'no');
    }

    $all_overrides[$team_name] = $points;

    update_option("tsl_points_overrides", $all_overrides);
}

function tsl_get_questions_and_team_answers($competition_name, $team_name)
{
    return tsl_sql_questions_and_team_answers("team", $competition_name, $team_name);
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
        'team_name%', 'age_group%');

    $results = $wpdb->get_results($query);
    return $results;

}

function tsl_grades_automated($competition_name, $team_name)
{
    $response = array();
    $correct_answers = tsl_get_competition_answers($competition_name);
    $questions_and_submitted_answers = tsl_get_questions_and_team_answers($competition_name, $team_name);
    foreach ($questions_and_submitted_answers as $question_and_submitted_answer) {
        $question_key = $question_and_submitted_answer->question_key;
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
    $score = 0.0;
    $auto_points = tsl_grades_automated($competition_name, $team_name);
    $override_points = tsl_get_grades_overrides($team_name);
    foreach ($auto_points as $question_key => $points) {
        if (!empty($override_points[$question_key])) {
            $score += $override_points[$question_key];
        } else {
            $score += $points;
        }
    }
    return $score;
}