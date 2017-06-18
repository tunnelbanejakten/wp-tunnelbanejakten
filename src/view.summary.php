<?php

function tsl_print_overview_report($competition_name)
{
    $teams = tsl_get_team_list();

    $age_group_top_score = array();
    foreach ($teams as $team) {
        $score = tsl_grade_final($competition_name, $team->team_name);
        $team->score = $score;
        if ($age_group_top_score[$team->age_group] < $score) {
            $age_group_top_score[$team->age_group] = $score;
        }
    }

    usort($teams, function ($a, $b) {
        $age_group_cmp = strcmp($a->age_group, $b->age_group);
        $sub_cmp = strcmp($a->team_name, $b->team_name);
//        $sub_cmp = strcmp($a->score, $b->score);
        return $age_group_cmp == 0 ? $sub_cmp : $age_group_cmp;
    });

    $last_age_group = null;

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

    printf('<h1><a href="%s">TSL</a> &raquo; Tävling <a href="%s">%s</a> &raquo; Poängställning</h1>',
        $start_url,
        $competition_url,
        $competition_name);
    printf('<table style="border-collapse: collapse;"><tbody>');

    foreach ($teams as $team) {
        if ($last_age_group != $team->age_group) {
            printf('<tr><td colspan="3">%s</td></tr>', $team->age_group);
        }
        $url = add_query_arg(array(
            'tsl_report' => 'nextgen',
            'tsl_team' => $team->team_name
        ));
        printf('' .
            '<tr>' .
            '<td><a href="%s">%s</a></td>' .
            '<td>%s</td>' .
            '<td>%s</td>' .
            '</tr>',
            $url,
            $team->team_name,
            tsl_grade_final($competition_name, $team->team_name),
            $team->score > 0 && $age_group_top_score[$team->age_group] == $team->score ? 'Bäst i klassen' : '');

        $last_age_group = $team->age_group;
    }
    printf('</tbody></table>');
}
