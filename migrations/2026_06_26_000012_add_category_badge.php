<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Per-category FoF badge: publishing a project in a category can award that
 * category's badge (e.g. Book -> Writer, Game -> Developer). Null = no badge.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('project_categories') && ! $schema->hasColumn('project_categories', 'badge_id')) {
            $schema->table('project_categories', function (Blueprint $t) {
                $t->unsignedInteger('badge_id')->nullable();
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasColumn('project_categories', 'badge_id')) {
            $schema->table('project_categories', function (Blueprint $t) {
                $t->dropColumn('badge_id');
            });
        }
    },
];
