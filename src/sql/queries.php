<?php
const SQL_QUESTIONS_AND_TEAM_ANSWERS = <<<SQL
SELECT
  coalesce(frm_parent.name, frm.name) form_name,
  f.name                              question_text,
  f.field_key                         question_key,
  f.type                              type,
  substr(answers.meta_value, 1, 100)  submitted_answer
FROM
  wp_frm_fields f
  INNER JOIN wp_frm_forms frm
    ON f.form_id = frm.id
  LEFT JOIN wp_frm_forms frm_parent
    ON frm.parent_form_id = frm_parent.id
  LEFT JOIN wp_frm_item_metas answers
    ON f.id = answers.field_id AND answers.item_id IN (SELECT m.item_id
                                                       FROM
                                                         wp_frm_item_metas m
                                                         INNER JOIN wp_frm_fields f
                                                           ON f.id = m.field_id
                                                         LEFT JOIN wp_users u
                                                           ON u.id = m.meta_value AND f.type = 'user_id'
                                                         LEFT JOIN wp_usermeta um
                                                             ON um.user_id = m.meta_value AND um.meta_key = 'nickname' AND f.type = 'user_id'
                                                       WHERE
                                                         f.field_key LIKE %s
                                                         AND coalesce(um.meta_value, u.display_name, m.meta_value) = %s)
WHERE (f.form_id IN (SELECT id
                     FROM wp_frm_forms
                     WHERE form_key LIKE %s)
       OR f.form_id IN (SELECT id
                        FROM wp_frm_forms
                        WHERE parent_form_id IN (SELECT id
                                                 FROM wp_frm_forms
                                                 WHERE form_key LIKE %s)))
      AND f.field_key NOT LIKE %s
      AND f.type NOT IN ('end_divider', 'user_id')
ORDER BY coalesce(frm_parent.id, frm.id), f.field_order
SQL;

const SQL_TEAM_LIST = <<<SQL
SELECT
  m.meta_value  team_name,
  um.user_id    user_id,
  m2.meta_value age_group
FROM wp_frm_item_metas m
  INNER JOIN wp_frm_fields f
    ON f.id = m.field_id
  INNER JOIN wp_frm_forms frm
    ON f.form_id = frm.id
  LEFT JOIN wp_frm_forms frm_parent
    ON frm.parent_form_id = frm_parent.id
  INNER JOIN wp_frm_item_metas m2
    ON m.item_id = m2.item_id
  LEFT JOIN wp_usermeta um
    ON (um.meta_value = m.meta_value AND um.meta_key = 'nickname')
WHERE (
        frm.form_key LIKE %s
        OR
        frm_parent.form_key LIKE %s
      )
      AND m.field_id IN (SELECT id
                         FROM wp_frm_fields
                         WHERE field_key LIKE %s)
      AND m2.field_id IN (SELECT id
                          FROM wp_frm_fields
                          WHERE field_key LIKE %s)
ORDER BY m.meta_value
SQL;

const SQL_TSL_FORMS = <<<SQL
SELECT 
  form_key
FROM 
  wp_frm_forms 
WHERE 
  form_key LIKE 'tsl-%-%'
SQL;

const SQL_TSL_COMPETITION_FORMS_AND_QUESTION_COUNT = <<<SQL
SELECT
  coalesce(frm_parent.form_key, frm.form_key) form_key,
  coalesce(frm_parent.name, frm.name)         form_name,
  count(f.field_key)                          question_count
FROM
  wp_frm_fields f
  INNER JOIN wp_frm_forms frm
    ON f.form_id = frm.id
  LEFT JOIN wp_frm_forms frm_parent
    ON frm.parent_form_id = frm_parent.id
WHERE (f.form_id IN (SELECT id
                     FROM wp_frm_forms
                     WHERE form_key LIKE %s)
       OR f.form_id IN (SELECT id
                        FROM wp_frm_forms
                        WHERE parent_form_id IN (SELECT id
                                                 FROM wp_frm_forms
                                                 WHERE form_key LIKE %s)))
      AND f.field_key NOT LIKE %s
      AND f.type NOT IN ('end_divider', 'user_id')
GROUP BY
  coalesce(frm_parent.form_key, frm.form_key),
  coalesce(frm_parent.name, frm.name)
SQL;

const SQL_ANSWERS_PER_SECTION_AND_TEAM = <<<SQL
SELECT
  coalesce(question_form_parent.form_key,
           question_form.form_key)                          form_key,
  coalesce(user_who_submitted_nickname.meta_value,
           user_who_submitted.display_name,
           left(other_answer_in_submission.meta_value, 20)) team,
  count(*)                                                  number_of_answers
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
        other_fields_in_same_submission.field_key LIKE %s)

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
                        WHERE form_key LIKE %s)
   OR question.form_id IN (SELECT id
                           FROM wp_frm_forms
                           WHERE parent_form_id IN (SELECT id
                                                    FROM wp_frm_forms
                                                    WHERE form_key LIKE %s)))
  # Skip "questions" which we know are irrelevant:
  AND question.field_key NOT LIKE %s

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
GROUP BY
  # team:
  coalesce(user_who_submitted_nickname.meta_value,
           user_who_submitted.display_name,
           left(other_answer_in_submission.meta_value, 20)),

  # form key:
  coalesce(question_form_parent.form_key, question_form.form_key)
ORDER BY 1
SQL;
