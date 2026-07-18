<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Lets an author declare that a project was created with the help of AI tools.
 * When set, a disclaimer is shown on the project card and detail page.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('projects') && ! $schema->hasColumn('projects', 'made_with_ai')) {
            $schema->table('projects', function (Blueprint $t) {
                $t->boolean('made_with_ai')->default(false);
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasColumn('projects', 'made_with_ai')) {
            $schema->table('projects', function (Blueprint $t) {
                $t->dropColumn('made_with_ai');
            });
        }
    },
];
