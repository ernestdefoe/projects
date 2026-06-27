<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Per-tag restriction: a custom field or button slot can be limited to certain
 * categories. A null/empty list means "available for every category".
 */
return [
    'up' => function (Builder $schema) {
        foreach (['project_fields', 'project_buttons'] as $table) {
            if ($schema->hasTable($table) && ! $schema->hasColumn($table, 'category_ids')) {
                $schema->table($table, function (Blueprint $t) {
                    $t->json('category_ids')->nullable();
                });
            }
        }
    },
    'down' => function (Builder $schema) {
        foreach (['project_fields', 'project_buttons'] as $table) {
            if ($schema->hasColumn($table, 'category_ids')) {
                $schema->table($table, function (Blueprint $t) {
                    $t->dropColumn('category_ids');
                });
            }
        }
    },
];
