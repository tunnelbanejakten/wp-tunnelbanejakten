# TODO: Create tables using https://developer.wordpress.org/reference/functions/dbdelta/

CREATE TABLE wp_tsl_answers (
  frm_field_key            VARCHAR(100) REFERENCES wp_frm_fields (field_key),
  grading_policy           VARCHAR(50),
  grading_policy_parameter VARCHAR(1000),
  points                   FLOAT,
  PRIMARY KEY (frm_field_key)
);

CREATE TABLE wp_tsl_overrides (
  frm_items_id  INT(11) REFERENCES wp_frm_items (id),
  frm_field_key VARCHAR(100) REFERENCES wp_frm_fields (field_key),
  points        FLOAT,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (frm_items_id, frm_field_key)
);

CREATE TABLE wp_tsl_check_timestamps (
  competition_key VARCHAR(100),
  team_name       VARCHAR(100),
  checked_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (competition_key, team_name)
);