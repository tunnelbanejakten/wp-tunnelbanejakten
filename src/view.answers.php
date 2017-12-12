<?php

include_once 'sql/queries.php';

function tsl_print_answers_form($competition_name)
{
    $question_types = [
        QUESTION_GRADING_TYPE_IGNORE => "Rätta inte",
        QUESTION_GRADING_TYPE_SUBMITTED_ANSWER_IS_POINTS => "Svaret är poängen",
        QUESTION_GRADING_TYPE_ONE_OF => "Ge poäng för ett av dessa:",
        QUESTION_GRADING_TYPE_ALL_OF => "Ge poäng om alla dessa angivits:"
    ];

    $question_type_descriptions = [
        QUESTION_GRADING_TYPE_IGNORE => "Detta är inte något som poängsätts över huvud taget.",
        QUESTION_GRADING_TYPE_SUBMITTED_ANSWER_IS_POINTS => "Svaret är i själva verket lagets poäng. " .
            "Är till för formulären som funktionärerna använder. Kräver ingen parameter. Poängfältet är maxpoäng.",
        QUESTION_GRADING_TYPE_ONE_OF => 'Laget får full poäng om det skickat in ett av alternativen som angetts i ' .
            'Parameter-fältet. Flera korrekta svar kan anges och ska då skiljas med ett semikolon, ex. "Antilop;antelop". ' .
            'Bra för frågor som bara har ett svar men där stavningen kan vara knepig.',
        QUESTION_GRADING_TYPE_ALL_OF => "Laget får full poäng om det skickat in samtliga av alternativen som " .
            "angetts i Parameter-fältet. De förväntade svaren skiljs åt med ett semikolon. Används för fotoserier där " .
            "poäng bara delas ut om alla fotografier i serien har rapporterats in."
    ];

    $entries = tsl_sql_questions('team', $competition_name);

    if ($_POST['tsl_action'] == 'save') {
        $updated_answers = array();
        foreach ($entries as $entry) {
            $type = $_POST['question_' . $entry->question_key . '_type'];
            if (in_array($type, array_keys($question_types))) {
                $answer = array();
                $answer['type'] = $type;

                $params = $_POST['question_' . $entry->question_key . '_params'];
                if (!empty($params)) {
                    $answer['params'] = explode(';', $params);
                }
                $points = $_POST['question_' . $entry->question_key . '_points'];
                if (!empty($points) && intval($points) > 0) {
                    $answer['points'] = intval($points);
                }
                $updated_answers[$entry->question_key] = $answer;
            }
        }
        tsl_update_competition_answers($competition_name, $updated_answers);
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

    printf('<h1><a href="%s">TSL</a> &raquo; Tävling <a href="%s">%s</a> &raquo; Rätt svar</h1>',
        $start_url,
        $competition_url,
        $competition_name);

    printf('<p><small>För varje fråga kan du välja om, och hur, den ska rättas och poängsättas: <br>%s</small></p>', join('<br>', array_map(function ($type, $descr) {
        return "<strong>$type</strong>: $descr";
    }, array_values($question_types), array_values($question_type_descriptions))));

    printf('<form method="post" action="%s">', add_query_arg());

    printf('<table style="border-collapse: collapse;"><tbody>');

    printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', 'Fråga (id)', 'Poängpolicy', 'Parameter', 'Poäng');

    $all_answers = tsl_get_competition_answers($competition_name);

    if (!isset($all_answers)) {
        printf('<p>%s</p>', 'Could not load page because answer configuration has not been saved correctly.');
        return;
    }

    $prev_form_name = null;
    foreach ($entries as $entry) {
        if ($prev_form_name != $entry->form_name) {
            printf('<tr><td colspan="4"><strong>%s</strong></td></tr>', $entry->form_name);
        }

        $answer_config = $all_answers[$entry->question_key];

        if (isset($answer_config) && in_array($answer_config['type'], array_keys($question_types))) {
            $answer_type = $answer_config['type'];
            $answer_params = !empty($answer_config['params']) ? join(';', $answer_config['params']) : '';
            $answer_points = $answer_config['points'];
        } else {
            $answer_type = QUESTION_GRADING_TYPE_IGNORE;
            $answer_params = '';
            $answer_points = '';
        }
        $options = join(array_map(function ($type, $label) use ($answer_type) {
            return sprintf('<option value="%s" %s>%s</option>',
                $type,
                $type == $answer_type ? 'selected="selected"' : '',
                $label);
        }, array_keys($question_types), array_values($question_types)));

        printf('' .
            '<tr>' .
            '<td><kbd>%s</kbd>: %s</td>' .
            '<td><select name="question_%s_type">%s</select></td>' .
            '<td><input name="question_%s_params" type="text" size="20" value="%s"></td>' .
            '<td><input name="question_%s_points" type="number" size="5" value="%s"></td>' .
            '</tr>', $entry->question_key, $entry->question_text, $entry->question_key, $options, $entry->question_key, $answer_params, $entry->question_key, $answer_points);
        $prev_form_name = $entry->form_name;
    }

    printf('</tbody></table>');

    printf('<button class="button button-primary" type="submit" name="tsl_action" value="save">Save</button>');

    printf('</form>');
}

function tsl_sql_questions($team_form_field_key_prefix = 'team', $competition_forms_key_prefix = 'tsl18')
{
    global $wpdb;
    $query = $wpdb->prepare(SQL_QUESTIONS_AND_TEAM_ANSWERS,
        "$team_form_field_key_prefix%",
        "NON-EXISTENT-TEAM-NAME",
        "tsl-$competition_forms_key_prefix-%",
        "tsl-$competition_forms_key_prefix-%",
        "$team_form_field_key_prefix%");
    $results = $wpdb->get_results($query);
    return $results;
}
