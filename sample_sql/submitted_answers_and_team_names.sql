# This query lists all submitted answers for the competition "test".
# Each entry contains both the submitted answer and the team it belongs to.

SELECT
  coalesce(question_form_parent.form_key, question_form.form_key) form_key,
  left(question.name, 20)                                         question_text,
  question.field_key                                              question_key,
  left(answer.meta_value, 20)                                     question_submitted_answer,


  coalesce(user_who_submitted_nickname.meta_value,
           user_who_submitted.display_name,
           left(other_answer_in_submission.meta_value, 20))       team

#   left(answer_item_meta.meta_value, 20)       reported_for_team,
#   answer_item_field.field_key                 reported_for_team_question_key,
#   answer_item_field.type                      ehhh_field_type,
#   u.display_name                              reported_by_team_display_name,
#   um.meta_value                               reported_by_team_nick_name
FROM
  wp_frm_fields question
  INNER JOIN wp_frm_item_metas answer
    ON question.id = answer.field_id

  # Get the name for the form (either directly or indirectly for the parent form)
  INNER JOIN wp_frm_forms question_form
    ON question.form_id = question_form.id
  LEFT JOIN wp_frm_forms question_form_parent
    ON question_form.parent_form_id = question_form_parent.id


  # Get team name from form field (for forms used by instructors)
  LEFT JOIN wp_frm_item_metas other_answer_in_submission
    ON answer.item_id = other_answer_in_submission.item_id
  LEFT JOIN wp_frm_fields other_fields_in_same_submission
    ON (other_answer_in_submission.field_id = other_fields_in_same_submission.id AND
        other_fields_in_same_submission.field_key LIKE 'team%')

  # Get team name from user name (for forms used by the teams themselves)
  LEFT JOIN wp_users user_who_submitted
    ON user_who_submitted.id = other_answer_in_submission.meta_value AND
       other_fields_in_same_submission.type = 'user_id'
  LEFT JOIN wp_usermeta user_who_submitted_nickname
    ON user_who_submitted_nickname.user_id = other_answer_in_submission.meta_value AND
       user_who_submitted_nickname.meta_key = 'nickname' AND
       other_fields_in_same_submission.type = 'user_id'

WHERE
  # Get questions/answers for a specific form (including "sub-forms")
  (question.form_id IN (SELECT id
                        FROM wp_frm_forms
                        WHERE form_key LIKE 'tsl-test-%')
   OR question.form_id IN (SELECT id
                           FROM wp_frm_forms
                           WHERE parent_form_id IN (SELECT id
                                                    FROM wp_frm_forms
                                                    WHERE form_key LIKE 'tsl-test-%')))
  # Skip "questions" which we know are irrelevant:
  AND question.field_key NOT LIKE 'team%'

  # Skip "questions" which we know are irrelevant:
  AND question.type NOT IN ('end_divider', 'user_id')

  AND (
    # We join with other_answer_in_submission to get the team name for each submitted answer, nothing else.
    # Therefore we add conditions to only keep rows derived from other_answer_in_submission which help us
    # determine the team name.
    other_fields_in_same_submission.field_key IS NOT NULL OR
    user_who_submitted.display_name IS NOT NULL OR
    user_who_submitted_nickname.meta_value IS NOT NULL
  )
