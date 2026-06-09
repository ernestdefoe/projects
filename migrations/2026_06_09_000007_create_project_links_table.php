<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('project_links')) {
            return;
        }

        $schema->create('project_links', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('button_id')->nullable(); // null = ad-hoc link (when free links are allowed)
            $table->string('url', 600);
            $table->string('label', 100)->nullable();         // custom label override
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('project_id');

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('button_id')->references('id')->on('project_buttons')->nullOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('project_links');
    },
];
