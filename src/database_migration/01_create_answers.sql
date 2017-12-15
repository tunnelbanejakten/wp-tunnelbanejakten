CREATE TABLE wp_tsl_answers (
  frm_field_key            VARCHAR(100) REFERENCES wp_frm_fields (field_key),
  grading_policy           VARCHAR(50),
  grading_policy_parameter VARCHAR(1000),
  points                   FLOAT,
  PRIMARY KEY (frm_field_key)
);
