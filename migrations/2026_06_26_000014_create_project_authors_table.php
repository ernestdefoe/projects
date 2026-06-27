<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Co-authors of a project. Each row is EITHER a forum user (user_id set) OR a
 * free-text name (name set) — letting a project credit collaborators who may or
 * may not have a forum account. Co-authors are display-only (no edit rights).
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('project_authors')) {
            $schema->create('project_authors', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('project_id')->index();
                $t->unsignedInteger('user_id')->nullable(); // users.id is INT UNSIGNED
                $t->string('name')->nullable();             // free-text co-author
                $t->unsignedInteger('position')->default(0);
            });
        }
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('project_authors');
    },
];
