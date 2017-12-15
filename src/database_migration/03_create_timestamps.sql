CREATE TABLE wp_tsl_check_timestamps (
  competition_key VARCHAR(100),
  team_name       VARCHAR(100),
  checked_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (competition_key, team_name)
);