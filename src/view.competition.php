<?php

include_once 'sql/queries.php';

function tsl_print_competition_page($competition_name)
{
    $start_url = add_query_arg(array(
        'tsl_report' => '',
        'tsl_competition' => '',
        'tsl_team' => ''
    ));
    printf('<h1><a href="%s">TSL</a> &raquo; Tävling %s</h1>', $start_url, $competition_name);

    printf('<h2>Rapporter och inställningar</h2>');

    $url = add_query_arg(array(
        'tsl_report' => 'points'
    ));
    printf('<p><a href="%s">Poängställning</a></p>', $url);
    $url = add_query_arg(array(
        'tsl_report' => 'answers'
    ));
    printf('<p><a href="%s">Rätt svar</a></p>', $url);
    $url = add_query_arg(array(
        'tsl_report' => 'sendmessage'
    ));
    printf('<p><a href="%s">Skicka meddelande</a></p>', $url);
    $url = add_query_arg(array(
        'tsl_report' => 'users'
    ));
    printf('<p><a href="%s">Anv&auml;ndarkonton</a></p>', $url);


    $sections = tsl_get_competitions_forms_and_question_count('team', $competition_name);

    $items = tsl_get_answers_per_section_and_team($competition_name);

    printf('<h2>Lag</h2>', $url);
    printf('<p><span class="dashicons dashicons-yes"></span> betyder att något godkänt svaren. <br><span class="dashicons dashicons-warning"></span> betyder att svar har ändrats sedan någon kontrollerade svaren senast.</p>');
    printf('<table>');
    printf('<thead><tr>%s</tr></thead>', join(array_map(
        function ($item) {
            return '<td>' . $item . '</td>';
        },
        array_merge(['Lag'], array_map(
            function ($section) {
                return $section->form_name;
            }, $sections), ['Rättad']))));
    printf('<tbody>');
    $teams = tsl_get_competition_teams($competition_name);

    foreach ($teams as $team) {
        if ($team->team_name) {
            $url = add_query_arg(array(
                'tsl_report' => 'nextgen',
                'tsl_team' => $team->team_name
            ));
            printf('<tr><td><a href="%s">%s</a></td>%s</tr>', $url, $team->team_name, join("", array_map(
                function ($section) use ($items, $team, $competition_name) {
                    $matches = array_filter($items, function ($item) use ($team, $section) {
                        return $item->team == $team->team_name && $item->form_key == $section->form_key;
                    });
                    $first_match = reset($matches);
                    $questions_answered = intval($first_match->number_of_answers);
                    $last_update = $first_match->last_update;
                    $questions_total = $section->question_count;
                    if ($questions_total > 0) {
                        $percent_done = round(100.0 * $questions_answered / $questions_total);
                    } else {
                        $percent_done = 0;
                    }

                    $last_checked = tsl_get_checked($competition_name, $team->team_name);

                    return sprintf('<td><div style="display: inline-block; height: 1.5em; border: 1px solid rgba(0,0,0,0.1); width: 10em; line-height: 1.5em;"><div style="display: inline-block; height: 1.5em; background-color: rgba(0,0,0,0.1); width: %d%%">%s</div></div></td>', $percent_done, $percent_done > 0 ? ($last_update < $last_checked ? '<span style="color: green" class="dashicons dashicons-yes"></span>' : '<span style="color: orange" class="dashicons dashicons-warning"></span>') : '');
                }, $sections)));
        } else {
            printf('<tr><td>%s</td></tr>', $team->team_name);
        }
    }
    printf('</tbody></table>');
}
