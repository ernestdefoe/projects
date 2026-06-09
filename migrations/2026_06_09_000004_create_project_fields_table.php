<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('project_fields')) {
            return;
        }

        // Admin-defined custom parameters (e.g. Genre, Age rating, Release date).
        $schema->create('project_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('key', 100);
            $table->string('type', 20)->default('text'); // text|textarea|number|date|url|select|boolean
            $table->text('options')->nullable();          // JSON array of choices for type=select
            $table->string('icon', 100)->nullable();
            $table->string('prefix', 30)->nullable();     // optional display prefix (e.g. "$")
            $table->string('suffix', 30)->nullable();     // optional display suffix (e.g. "pages")
            $table->boolean('is_required')->default(false);
            $table->boolean('on_card')->default(true);    // show on the card (vs detail page only)
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique('key');
            $table->index('position');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('project_fields');
    },
];
