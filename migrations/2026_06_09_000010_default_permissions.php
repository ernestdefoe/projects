<?php

use Illuminate\Database\Schema\Builder;

/**
 * Sensible out-of-the-box permissions:
 *   - Members (group 3) may create projects.
 *   - Moderators (group 4) may moderate them.
 * Admins implicitly have everything. Admins can change all of this in the
 * Permissions grid afterwards.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $grants = [
            [3, 'projects.create'],
            [4, 'projects.moderate'],
        ];

        foreach ($grants as [$groupId, $permission]) {
            $exists = $db->table('group_permission')
                ->where('group_id', $groupId)
                ->where('permission', $permission)
                ->exists();

            if (! $exists) {
                $db->table('group_permission')->insert([
                    'group_id'   => $groupId,
                    'permission' => $permission,
                ]);
            }
        }
    },

    'down' => function (Builder $schema) {
        $schema->getConnection()->table('group_permission')
            ->whereIn('permission', ['projects.create', 'projects.moderate', 'projects.skipModeration'])
            ->delete();
    },
];
