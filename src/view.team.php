<?php

include_once 'sql/queries.php';

const COLOR_RED = '#ffcc99';
const COLOR_YELLOW = '#ffff99';
const COLOR_GREEN = '#aaeeaa';

function tsl_print_nextgen_report($competition_name, $team_name)
{
    $entries = tsl_sql_questions_and_team_answers('team', $competition_name, $team_name);

    if ($_POST['tsl_action'] == 'save_points_overrides') {
        foreach ($entries as $entry) {
            $key = $entry->entry_id . '-' . $entry->question_key;
            $override = $_POST['question_' . $key . '_points_override'];

            if (!empty($override) && floatval($override) > 0) {
                tsl_set_points_override($entry->entry_id, $entry->question_key, floatval($override));
                $entry->override_points = floatval($override);
            } elseif ($entry->override_points > 0) {
                tsl_unset_points_override($entry->entry_id, $entry->question_key);
                $entry->override_points = null;
            }
        }

        tsl_set_checked($competition_name, $team_name);
    }

    $check_timestamp = tsl_get_checked($competition_name, $team_name);

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

    printf('<table style="border-collapse: collapse;" cellpadding="3">');
    printf('<thead><tr><td colspan="5" style="text-align: right"><button class="button button-primary" type="submit" name="tsl_action" value="save_points_overrides">Spara korrigerade poäng och markera svaren som rättade</button></td></tr></thead>');
    printf('<tfoot><tr><td colspan="5" style="text-align: right"><button class="button button-primary" type="submit" name="tsl_action" value="save_points_overrides">Spara korrigerade poäng och markera svaren som rättade</button></td></tr></tfoot>');
    printf('<tbody>');
    printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', 'Fråga', 'Rätt svar', 'Inskickat', 'Korr. poäng', 'Poäng');

    $points_auto_evaluated = tsl_grades_automated($competition_name, $team_name);

    $correct_answers = tsl_get_competition_answers($competition_name);

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
                '<td>%s</td>' .
                '<td><span style="background-color: %s; padding: 0.4em 0.6em;">%s %s</span></td>' .
                '</tr>',
                $entry->question_key,
                $entry->question_text,
                $correct_answer,
                $submitted_answer,
                empty($entry->entry_id) ? '' :
                    sprintf('<input type="text" name="question_%s_points_override" value="%s" size="5"/>%s',
                        $entry->entry_id . '-' . $entry->question_key,
                        $entry->override_points,
                        $entry->entry_time > $check_timestamp ? '<span style="color: orange" class="dashicons dashicons-warning"></span>' : '<span style="color: green" class="dashicons dashicons-yes"></span>'
                    ),
                empty($submitted_answer) ? COLOR_YELLOW : ($auto_points == $max_points ? COLOR_GREEN : COLOR_RED),
                (float)$auto_points,
                $max_points > 0 ? "av $max_points" : "");
        }
        $prev_form_name = $entry->form_name;
    }

    printf('</tbody></table>');

    printf('</form>');
}
