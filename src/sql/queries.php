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
  INNER JOIN wp_frm_item_metas m2 ON m.item_id = m2.item_id
  LEFT JOIN wp_usermeta um ON (um.meta_value = m.meta_value AND um.meta_key = 'nickname')
WHERE m.field_id IN (SELECT id
                     FROM wp_frm_fields
                     WHERE name = 'Gruppnamn')
      AND m2.field_id IN (SELECT id
                          FROM wp_frm_fields
                          WHERE field_key = 'xyzrk')
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
