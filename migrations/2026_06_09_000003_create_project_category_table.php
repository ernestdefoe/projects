<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('project_category')) {
            return;
        }

        // Many-to-many: a project can carry several categories/tags. The
        // "main" one for the profile badge is stored on projects.primary_category_id.
        $schema->create('project_category', function (Blueprint $table) {
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('category_id');

            $table->primary(['project_id', 'category_id']);
            $table->index('category_id');

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('project_categories')->cascadeOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('project_category');
    },
];
