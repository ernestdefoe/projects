<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('project_field_values')) {
            return;
        }

        $schema->create('project_field_values', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('field_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'field_id'], 'project_field_unique');
            $table->index('field_id');

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('field_id')->references('id')->on('project_fields')->cascadeOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('project_field_values');
    },
];
