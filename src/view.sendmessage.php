<?php

const NOT_APPLICABLE = 'N/A';

$noop = function () {
    return NOT_APPLICABLE;
};

$name = function ($in) {
    return $in->team_name;
};

$phone = function ($in) {
    return tsl_fix_phone_number($in->phone_primary);
};

$age_group = function ($in) {
    return $in->age_group;
};

$user = function ($in) {
    return !empty($in->user_name) ? $in->user_name : NOT_APPLICABLE;
};

$pw = function ($in) {
    global $user;
    $username = $user($in);
    if ($username != NOT_APPLICABLE) {
        return tsl_derive_password($username);
    } else {
        return NOT_APPLICABLE;
    }
};

$valueGetters = array(
    "NAME" => $name,
    "AGE_GROUP" => $age_group,
    "CONTACT_PHONE" => $phone,
    "USER_NAME" => $user,
    "USER_DEFAULT_PASSWORD" => $pw
);

function translate_message($messageTemplate, $props)
{
    global $valueGetters;
    $replace_pairs = array_combine(
        array_keys($valueGetters),
        array_map(function ($valueGetter) use ($props) {
            return $valueGetter($props);
        }, array_values($valueGetters)));
//    print_r($replace_pairs);
    return strtr($messageTemplate, $replace_pairs);
}

function tsl_fix_phone_number($number)
{
    $no_leading_zero = ltrim(trim(preg_replace('/[^0-9+]/', '', $number)), '0');
    if ($no_leading_zero[0] == '7') {
        return "+46$no_leading_zero";
    } else if (substr($no_leading_zero, 0, 2) == '46') {
        return "+$no_leading_zero";
    } else {
        return $no_leading_zero;
    }
}

// See https://www.46elks.se/tutorials/send-sms-with-php
function tsl_send_message($message, $to, $username46Elks, $password46Elks)
{
    if (!preg_match('/^\+46[^0][0-9]+$/', $to)) {
        return 'Telefonnummer m&aring:ste b&ouml;rja med +46 och bara inneh&aring;lla siffror.';
    }
    $sms = array(
        "from" => "TSL" /* Can be up to 11 alphanumeric characters */,
        "to" => $to,
        "message" => $message
    );
    $context = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Authorization: Basic ' .
                base64_encode($username46Elks . ':' . $password46Elks) . "\r\n" .
                "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($sms),
            'timeout' => 10
        )));

//    var_dump($context);

//    $response = file_get_contents("https://api.46elks.com/a1/SMS", false, $context);

//    var_dump($response);

    if (!strstr($http_response_header[0], "200 OK"))
        return $http_response_header[0];

//    return $response;
    return 'OK';
}

function tsl_send_messages_form($competition_name)
{
    global $valueGetters;
    $teams = tsl_get_competition_team_contacts($competition_name);
    $teams2 = tsl_get_competition_team_contacts_2($competition_name);

    $new_phone_numbers = array_combine(
        array_map(function ($team2) {
            return $team2->team_name;
        }, $teams2),
        array_map(function ($team2) {
            return $team2->phone_primary;
        }, $teams2));

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

    printf('<h1><a href="%s">TSL</a> &raquo; Tävling <a href="%s">%s</a> &raquo; Skicka meddelande</h1>',
        $start_url,
        $competition_url,
        $competition_name);


    printf('<form method="post" action="%s">', add_query_arg());

    if ($_POST['tsl_action'] == 'message_preview' || $_POST['tsl_action'] == 'message_send') {
        if ($_POST['tsl_action'] == 'message_send') {
            update_option('tsl_username_46elks', $_POST['username_46elks'], false);
            update_option('tsl_password_46elks', $_POST['password_46elks'], false);
        }

        printf('<input type="hidden" name="message_template" value="%s">', $_POST['message_template']);
        printf('<p>Anv&auml;ndarnamn hos 46Elks:<br><input type="text" name="username_46elks" value="%s" size="50"></p>', get_option('tsl_username_46elks'));
        printf('<p>L&ouml;senord hos 46Elks:<br><input type="text" name="password_46elks" value="%s" size="50"></p>', get_option('tsl_password_46elks'));
    } else {
        printf('<p><em>För att skicka SMS krävs ett konto hos <a href="https://46elks.com/" target="_blank">46elks</a>.</em></p>');
        printf('<p>Meddelandemall:<br><textarea name="message_template" cols="100" rows="5" %s>%s</textarea></p>',
            $_POST['tsl_action'] == 'message_preview' || $_POST['tsl_action'] == 'message_send' ? 'disabled' : '',
            empty($_POST['message_template']) ? "Hej NAME. Svara pa www.tunnelbanejakten.se/svara. \nAnvandarnamn: USER_NAME\nLosenord: USER_DEFAULT_PASSWORD" : $_POST['message_template']);
        printf('<p><small>Du kan anv&auml;nda de h&auml;r variablerna i ditt meddelande: %s.</small></p>', join(', ', array_keys($valueGetters)));
    }


    printf('<button class="button button-primary" type="submit" name="tsl_action" value="message_preview" %s>F&ouml;rhandsgranska</button> ',
        $_POST['tsl_action'] == 'message_preview' || $_POST['tsl_action'] == 'message_send' ? 'disabled' : '');
    printf('<button class="button button-secondary" type="submit" name="tsl_action" value="" %s>Avbryt</button> ',
        $_POST['tsl_action'] != 'message_preview' ? 'disabled' : '');
    printf('<button class="button button-primary" type="submit" name="tsl_action" value="message_send" %s>Skicka</button> ',
        $_POST['tsl_action'] != 'message_preview' ? 'disabled' : '');

    printf('<table style="border-collapse: collapse;" cellpadding="3"><tbody>');

    $selected_team_names = $_POST['selected_teams'];
    foreach ($teams as $team) {
        if ($last_age_group != $team->age_group) {
            printf('<tr><td colspan="3"><strong>Åldergrupp %s</strong></td></tr>', $team->age_group);
        }
        $id = uniqid();

        $is_selected = is_array($selected_team_names) && in_array($team->team_name, $selected_team_names);

        $phone_primary1 = tsl_fix_phone_number($team->phone_primary);
        $phone_primary2 = tsl_fix_phone_number($new_phone_numbers[$team->team_name]);
//        if (!empty($phone_primary2) && $phone_primary1 != $phone_primary2) {
//            printf('<p>Telefon andrades fran %s till %s.</p>', $phone_primary1, $phone_primary2);
//        }
        $phone_primary = !empty($phone_primary2) ? $phone_primary2 : $phone_primary1;
        $phone_secondary = tsl_fix_phone_number($team->phone_secondary);
        if ($phone_primary == $phone_secondary) {
            $phone_secondary = null;
        }

        $messageHtml = '';
        $statusHtml = '';
        if ($is_selected) {
            if ($_POST['tsl_action'] == 'message_preview' || $_POST['tsl_action'] == 'message_send') {
                $message = translate_message($_POST['message_template'], $team);
                $messageHtml = sprintf('<pre style="margin: 0">%s</pre>', $message);
                if ($_POST['tsl_action'] == 'message_send') {

                    if (!empty($phone_primary)) {
                        $api_response = tsl_send_message($message,
                            $phone_primary,
                            get_option('tsl_username_46elks'),
                            get_option('tsl_password_46elks'));
                        $statusHtml .= sprintf('<p>API-svar f&ouml;r SMS till %s: %s</p>', $phone_primary, $api_response);
                    }
                    if (!empty($phone_secondary)) {
                        $api_response = tsl_send_message($message,
                            $phone_secondary,
                            get_option('tsl_username_46elks'),
                            get_option('tsl_password_46elks'));
                        $statusHtml .= sprintf('<p>API-svar f&ouml;r SMS till %s: %s</p>', $phone_secondary, $api_response);
                    }
                }
            }
        }

        printf('' .
            '<tr>' .
            '<td valign="top"><input type="checkbox" name="selected_teams[]" value="%s" %s id="%s"/><label for="%s">%s</label></td>' .
            '<td valign="top"><kbd>%s</kbd> <kbd>%s</kbd></td>' .
            '<td valign="top">%s%s</td>' .
            '</tr>',
            $team->team_name,
            $is_selected ? 'checked' : '',
            $id,
            $id,
            $team->team_name,
            $phone_primary,
            $phone_secondary,
            $messageHtml,
            $statusHtml);

        $last_age_group = $team->age_group;
    }
    printf('</tbody></table>');
}
