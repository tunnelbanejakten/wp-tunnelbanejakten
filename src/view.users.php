<?php

const NOT_APPLICABLE = 'N/A';

$pw = function ($in) {
    global $user;
    $username = $user($in);
    if ($username != NOT_APPLICABLE) {
        return substr(md5($username), 0, 4);
    } else {
        return NOT_APPLICABLE;
    }
};

function tsl_users($competition_name)
{
    global $valueGetters;
    $teams = tsl_get_competition_team_contacts($competition_name);

//    print_r($teams);

    if ($_POST['tsl_action'] == 'create_users') {
        $selected_team_names = $_POST['selected_teams'];
        foreach ($selected_team_names as $selected_team_name) {
            printf('<p>Create user for team %s</p>', $selected_team_name);
            foreach ($teams as $team) {
                if ($team->team_name == $selected_team_name) {
                    if (empty($team->user_id)) {
                        $username = str_replace('+46', '0', tsl_fix_phone_number($team->phone_primary));
                        $password = tsl_derive_password($username);
                        printf('<p>User: %s</p>', $username);
                        printf('<p>Password: %s</p>', $password);
                        $userdata = array(
                            "user_pass" => $password,
                            "user_login" => $username,
                            "nickname" => $team->team_name,
                            "first_name" => $team->team_name,
                            "role" => "subscriber",
                        );
                        $create_response = wp_insert_user($userdata);
                        print_r($create_response);
                    } else {
                        printf('<p>User already exists for team (user: %s)</p>', $team->user_name);
                    }
                }
            }
        }
        $teams = tsl_get_competition_team_contacts($competition_name);
    } else if ($_POST['tsl_action'] == 'set_passwords') {
        $selected_team_names = $_POST['selected_teams'];
        foreach ($selected_team_names as $selected_team_name) {
            foreach ($teams as $team) {
                if ($team->team_name == $selected_team_name) {
                    if (!empty($team->user_id) && !empty($team->user_name)) {
                        $password = tsl_derive_password($team->user_name);
                        wp_set_password($password, $team->user_id);
                        printf('<p>Set password for team %s (user id %s, password %s)</p>', $selected_team_name, $team->user_id, $password);
                    } else {
                        printf('<p>User does not exist for team %s.</p>', $team->team_name);
                    }
                }
            }
        }
        $teams = tsl_get_competition_team_contacts($competition_name);
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

    printf('<h1><a href="%s">TSL</a> &raquo; Tävling <a href="%s">%s</a> &raquo; Anv&auml;ndarkonton</h1>',
        $start_url,
        $competition_url,
        $competition_name);

    printf('<form method="post" action="%s">', add_query_arg());
    printf('<table style="border-collapse: collapse;" cellpadding="3"><tbody>');

    foreach ($teams as $team) {
        if ($last_age_group != $team->age_group) {
            printf('<tr><td colspan="3"><strong>Åldergrupp %s</strong></td></tr>', $team->age_group);
        }
        $id = uniqid();
        printf('' .
            '<tr>' .
            '<td valign="top"><input type="checkbox" name="selected_teams[]" value="%s" id="%s"/><label for="%s">%s</label></td>' .
            '<td valign="top"><kbd>%s</kbd></td>' .
            '<td valign="top"><kbd>%s</kbd></td>    ' .
            '</tr>',
            $team->team_name,
            $id,
            $id,
            $team->team_name,
            $team->user_name,
            !empty($team->user_name) ? tsl_derive_password($team->user_name) : ''
        );

        $last_age_group = $team->age_group;
    }
    printf('</tbody></table>');
    printf('<button class="button button-primary" type="submit" name="tsl_action" value="create_users">Skapa anv&auml;ndare</button> ');
    printf('<button class="button button-primary" type="submit" name="tsl_action" value="set_passwords">S&auml;tt standardl&ouml;senord</button> ');
    printf('</form>');
}
