<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('project_buttons')) {
            return;
        }

        // Admin-defined button/link slots. Each project may fill in a URL per
        // slot; the URL can be constrained to an allow-list of domains.
        $schema->create('project_buttons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('label', 100);                  // default button label
            $table->string('key', 100);
            $table->string('icon', 100)->nullable();
            $table->text('allowed_domains')->nullable();    // JSON array of allowed host suffixes, e.g. ["youtube.com"]
            $table->boolean('allow_custom_label')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_primary')->default(false);  // render as the primary (filled) button
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique('key');
            $table->index('position');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('project_buttons');
    },
];
