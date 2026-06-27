<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Lets a member choose which of their projects is "featured" (drives the profile
 * header + the username badge). Without an explicit pick, FeaturedProject falls
 * back to the latest published one.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('projects') && ! $schema->hasColumn('projects', 'is_featured')) {
            $schema->table('projects', function (Blueprint $t) {
                $t->boolean('is_featured')->default(false);
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasColumn('projects', 'is_featured')) {
            $schema->table('projects', function (Blueprint $t) {
                $t->dropColumn('is_featured');
            });
        }
    },
];
