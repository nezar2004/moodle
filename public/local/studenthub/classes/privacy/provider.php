<?php
namespace local_studenthub\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/** Describe and export snapshots stored as Moodle user preferences. */
final class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\user_preference_provider {
    public static function get_metadata(collection $items): collection {
        $items->add_user_preference('local_studenthub_*', 'privacy:metadata:snapshot');
        return $items;
    }

    public static function export_user_preferences(int $userid): void {
        foreach (get_user_preferences(null, null, $userid) as $name => $value) {
            if (str_starts_with($name, 'local_studenthub_')) {
                writer::export_user_preference(
                    'local_studenthub', $name, $value, get_string('privacy:metadata:snapshot', 'local_studenthub'),
                );
            }
        }
    }
}
