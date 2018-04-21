<?php

function tsl_print_overview_report($competition_name)
{
    $teams = tsl_get_competition_teams($competition_name);

    $age_group_top_score = array();
    $top_of_age_group = array();
    $team_scores = array_combine(
        array_map(function ($team) {
            return $team->team_name;
        }, $teams),
        array_map(function ($team) use ($competition_name) {
            return tsl_grade_final($competition_name, $team->team_name);
        }, $teams));

    foreach ($teams as $team) {
        $score = $team_scores[$team->team_name];
        $team->score = $score;
        if ($age_group_top_score[$team->age_group] < $score) {
            $age_group_top_score[$team->age_group] = $score;
        }

        $top_of_age_group[$team->age_group][$score][] = $team->team_name;
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

    $age_group_team_count = array_count_values(array_map(function ($team) {
        return $team->age_group;
    }, $teams));

    $teams_count = array_sum($age_group_team_count);

    printf('<h2>Toppskiktet</h2>');

    printf('<table style="border-collapse: collapse;" cellpadding="3"><tbody>');

    foreach ($top_of_age_group as $age_group => $teams_per_score) {
        krsort($teams_per_score);
        printf('<tr><td colspan="2"><strong>Åldergrupp %s</strong></td></tr>', $age_group);
        $count = 3;
        foreach ($teams_per_score as $score => $teams_with_score) {
            if ($score > 0) {
                printf('<tr><td>%s poäng</td><td>%s</td></tr>', $score, join(", ", $teams_with_score));
                if (--$count < 1) {
                    break;
                }
            }
        }
    }

    printf('</tbody></table>');

    printf('<h2>Alla %d lag</h2>', $teams_count);
    printf('<table style="border-collapse: collapse;" cellpadding="3"><tbody>');

    foreach ($teams as $team) {
        if ($last_age_group != $team->age_group) {
            printf('<tr><td colspan="3"><strong>Åldergrupp %s (%d lag)</strong></td></tr>', $team->age_group, $age_group_team_count[$team->age_group]);
        }
        $url = add_query_arg(array(
            'tsl_report' => 'nextgen',
            'tsl_team' => $team->team_name
        ));
        printf('' .
            '<tr>' .
            '<td><a href="%s">%s</a></td>' .
            '<td>%s p</td>' .
            '<td>%s</td>' .
            '</tr>',
            $url,
            $team->team_name,
            $team_scores[$team->team_name],
            $team->score > 0 && $age_group_top_score[$team->age_group] == $team->score ? 'Bäst i klassen' : '');

        $last_age_group = $team->age_group;
    }
    printf('</tbody></table>');
}
