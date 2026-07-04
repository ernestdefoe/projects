<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Parameters (project fields) get a description, shown under the field's name
 * in the submission form — categories always had one, and per the discuss
 * thread parameters need explaining far more than categories do.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('project_fields') && ! $schema->hasColumn('project_fields', 'description')) {
            $schema->table('project_fields', function (Blueprint $t) {
                $t->string('description', 255)->nullable();
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasColumn('project_fields', 'description')) {
            $schema->table('project_fields', function (Blueprint $t) {
                $t->dropColumn('description');
            });
        }
    },
];
