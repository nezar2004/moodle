<?php
defined('MOODLE_INTERNAL') || die();

/** Upgrade the Student hub schema from its installed version. */
function xmldb_local_studenthub_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026111601) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_studenthub_notice');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('kind', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('oldtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('newtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('note', XMLDB_TYPE_TEXT);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('coursemodule', XMLDB_KEY_FOREIGN_UNIQUE, ['cmid'], 'course_modules', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026111601, 'local', 'studenthub');
    }

    return true;
}
