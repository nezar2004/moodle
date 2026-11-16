<?php
namespace local_studenthub;

/** Keep a per-student record of changes detected between visits. */
final class change_log {
    /** Get changes for visible items, recording the current state for the next visit. */
    public static function update(int $courseid, array $items): array {
        $key = 'local_studenthub_' . $courseid;
        $raw = get_user_preferences($key, '');
        $previous = $raw === '' ? null : json_decode($raw, true);
        $snapshot = [];
        $changes = $previous['changes'] ?? [];
        $now = time();

        foreach ($items as $id => $item) {
            $snapshot[$id] = ['due' => $item['due'], 'files' => $item['files']];
            if ($previous === null) {
                continue;
            }

            $old = $previous['items'][$id] ?? null;
            if (!$old) {
                $changes[] = ['id' => $id, 'type' => 'newitem', 'time' => $now];
                continue;
            }
            if ($old['due'] !== $item['due']) {
                $changes[] = [
                    'id' => $id, 'type' => 'duedatechanged', 'time' => $now,
                    'old' => $old['due'], 'new' => $item['due'],
                ];
            }
            if ($old['files'] !== $item['files']) {
                $changes[] = ['id' => $id, 'type' => 'fileschanged', 'time' => $now];
            }
        }

        $changes = array_values(array_filter($changes, static fn(array $change): bool => isset($items[$change['id']])));
        $changes = array_slice($changes, -30);
        $encoded = json_encode(['items' => $snapshot, 'changes' => $changes], JSON_THROW_ON_ERROR);
        if ($encoded !== $raw) {
            set_user_preference($key, $encoded);
        }

        return array_reverse($changes);
    }
}
