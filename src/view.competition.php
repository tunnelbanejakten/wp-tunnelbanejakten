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

    printf('<h2>Lag</h2>', $url);
    printf('<ul>');
    $teams = tsl_get_team_list();
    foreach ($teams as $team) {
        if ($team->team_name) {
            $url = add_query_arg(array(
                'tsl_report' => 'nextgen',
                'tsl_team' => $team->team_name
            ));
            printf('<li><a href="%s">%s</a></li>', $url, $team->team_name);
        } else {
            printf('<li>%s</li>', $team->team_name);
        }
    }
    printf('</ul>');
}

function tsl_get_team_list()
{
    global $wpdb;
    $results = $wpdb->get_results(SQL_TEAM_LIST);
    return $results;
}
