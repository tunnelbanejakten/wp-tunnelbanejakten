<?php

include_once 'sql/queries.php';

function tsl_print_start_page()
{
    printf('<h1>TSL</h1>');
    printf('<p>Välj tävling:</p>');
    printf('<ul>%s</ul>', join(array_map(function ($name) {
        $url = add_query_arg(array(
            'tsl_report' => 'competition',
            'tsl_competition' => $name
        ));
        return sprintf('<li><a href="%s">%s</a></li>', $url, $name);
    }, tsl_get_formgroups_list())));
    printf('<p><small>Den här listan baseras på vilka Formidable-formulär som heter tsl_<em>tävlingsnamn</em>_<em>delmoment</em>.</small></p>');
}


function tsl_get_formgroups_list()
{
    global $wpdb;
    $results = $wpdb->get_results(SQL_TSL_FORMS);
    return array_unique(array_map(function ($entry) {
        $prefix_length = strlen("tsl-");
        $groupname_end_pos = strpos($entry->form_key, '-', $prefix_length);
        return substr($entry->form_key, $prefix_length, $groupname_end_pos - $prefix_length);
    }, $results));
}
