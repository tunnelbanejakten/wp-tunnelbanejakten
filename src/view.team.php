<?php

include_once 'sql/queries.php';

const COLOR_RED = '#ff9999';
const COLOR_YELLOW = '#ffff99';
const COLOR_GREEN = '#99ff99';

function tsl_print_nextgen_report($competition_name, $team_name)
{
    $entries = tsl_sql_questions_and_team_answers('team', $competition_name, $team_name);

    if ($_POST['tsl_action'] == 'save_points_overrides') {
        $updated_overrides = tsl_get_grades_overrides($team_name);
        foreach ($entries as $entry) {
            $override = $_POST['question_' . $entry->question_key . '_points_override'];
            if (!empty($override) && floatval($override) > 0) {
                $updated_overrides[$entry->question_key] = floatval($override);
            } else {
                unset($updated_overrides[$entry->question_key]);
            }
        }
        tsl_update_grades_overrides($team_name, $updated_overrides);
    }

    $start_url = add_query_arg(array(
        'tsl_report' => '',
        'tsl_competition' => '',
        'tsl_team' => ''
    ));

    $competition_url = add_query_arg(array(
        'tsl_report' => 'competition',
        'tsl_competition' => $competition_name,
        'tsl_team' => ''
    ));

    printf('<h1><a href="%s">TSL</a> &raquo; Tävling <a href="%s">%s</a> &raquo; Lag <em>%s</em></h1>',
        $start_url,
        $competition_url,
        $competition_name,
        $team_name);

    printf('<form method="post" action="%s">', add_query_arg());

    printf('<table style="border-collapse: collapse;" cellpadding="3"><tbody>');

    printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', 'Fråga', 'Rätt svar', 'Inskickat', 'Beräknad poäng', 'Korrigerad poäng');

    $points_auto_evaluated = tsl_grades_automated($competition_name, $team_name);

    $correct_answers = tsl_get_competition_answers($competition_name);

    $points_overrides = tsl_get_grades_overrides($team_name);

    $prev_form_name = null;
    foreach ($entries as $entry) {
        if ($prev_form_name != $entry->form_name) {
            printf('<tr><td colspan="5"><h3>%s</h3></td></tr>', $entry->form_name);
        }
        $submitted_answer = unserialize($entry->submitted_answer);
        if ($submitted_answer !== false) {
            if (is_array($submitted_answer)) {
                $submitted_answer = join(', ', $submitted_answer);
            }
        } else {
            $submitted_answer = $entry->submitted_answer;
        }
        $correct_answer = $correct_answers[$entry->question_key]['params'];
        if (is_array($correct_answer)) {
            $correct_answer = join(", ", $correct_answer);
        } else {
            $correct_answer = "";
        }
        $max_points = $correct_answers[$entry->question_key]['points'];
        $is_readonly = QUESTION_GRADING_TYPE_IGNORE == $correct_answers[$entry->question_key]['type'];
        if ($is_readonly) {
            $auto_points = $points_auto_evaluated[$entry->question_key];
            printf('<tr>' .
                '<td colspan="5"><kbd>%s</kbd>: %s</td>' .
                '</tr>',
                $entry->question_key,
                $entry->question_text);
        } else {
            $auto_points = $points_auto_evaluated[$entry->question_key];
            printf('<tr>' .
                '<td><kbd>%s</kbd>: %s</td>' .
                '<td><small><em>%s</em></small></td>' .
                '<td>%s</td>' .
                '<td style="background-color: %s">%s %s</td>' .
                '<td><input type="text" name="question_%s_points_override" value="%s" size="5"/></td>' .
                '</tr>',
                $entry->question_key,
                $entry->question_text,
                $correct_answer,
                $submitted_answer,
                empty($submitted_answer) ? COLOR_YELLOW : ($auto_points == $max_points ? COLOR_GREEN : COLOR_RED),
                (float)$auto_points,
                $max_points > 0 ? "av $max_points" : "",
                $entry->question_key,
                $points_overrides[$entry->question_key]);
        }
        $prev_form_name = $entry->form_name;
    }

    printf('</tbody></table>');

    printf('<button type="submit" name="tsl_action" value="save_points_overrides">Save</button>');

    $final_score = tsl_grade_final($competition_name, $team_name);
    printf('<p>Totalpoäng: %s</p>', $final_score);

    printf('</form>');
}
