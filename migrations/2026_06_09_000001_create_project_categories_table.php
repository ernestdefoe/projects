<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('project_categories')) {
            return;
        }

        $schema->create('project_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('icon', 100)->nullable();   // FontAwesome class, e.g. "fas fa-book"
            $table->string('color', 20)->nullable();    // hex accent for the card/badge
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique('slug');
            $table->index('position');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('project_categories');
    },
];
