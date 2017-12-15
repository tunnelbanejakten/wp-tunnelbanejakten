<?php
/**
 * Alternative solution for database migration: https://developer.wordpress.org/reference/functions/dbdelta/
 */
function tsl_db_migrate()
{
    global $wpdb;
    $executed_scripts = get_option("tsl_db_migrations", []);

    $existing_scripts = glob(__DIR__ . '/database_migration/*.sql');
    sort($existing_scripts);
    error_log("Database migrate. STARTING.");
    foreach (array_keys($existing_scripts) as $key) {
        if (!isset($executed_scripts[$key])) {
            $migration_script_path = $existing_scripts[$key];
            error_log("Database migrate. Executing script " . $migration_script_path);
            $affected_rows = $wpdb->query(file_get_contents($migration_script_path));
            if ($affected_rows !== false) {
                $executed_scripts[] = $migration_script_path;
                update_option("tsl_db_migrations", $executed_scripts);
            } else {
                break;
            }
        }
    }
    error_log("Database migrate. DONE.");
}