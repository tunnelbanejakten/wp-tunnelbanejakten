CREATE TABLE wp_tsl_overrides (
  frm_items_id  INT(11) REFERENCES wp_frm_items (id),
  frm_field_key VARCHAR(100) REFERENCES wp_frm_fields (field_key),
  points        FLOAT,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (frm_items_id, frm_field_key)
);