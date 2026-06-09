<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'projects_featured')) {
            $schema->table('users', function (Blueprint $table) {
                // Denormalised JSON snapshot {id,title,slug,icon,color} of the
                // user's featured published project. Stored on the user so the
                // username badge renders with zero extra queries (no N+1 across
                // post streams). Maintained by the Project model's save/delete
                // hooks (see FeaturedProject service).
                $table->text('projects_featured')->nullable();
            });
        }
    },

    'down' => function (Builder $schema) {
        if ($schema->hasColumn('users', 'projects_featured')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropColumn('projects_featured');
            });
        }
    },
];
