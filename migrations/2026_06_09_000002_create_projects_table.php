<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('projects')) {
            return;
        }

        $schema->create('projects', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();             // author
            $table->unsignedInteger('primary_category_id')->nullable(); // "main tag" for the profile badge
            $table->unsignedInteger('discussion_id')->nullable();       // optional linked forum thread
            $table->string('title', 255);
            $table->string('slug', 255);
            $table->string('excerpt', 600)->nullable();                 // short card description
            $table->text('content')->nullable();                        // full body (forum-formatted)
            $table->string('image_path', 600)->nullable();              // card / hero image URL
            $table->string('status', 20)->default('pending');           // pending | published | rejected
            $table->string('rejection_reason', 500)->nullable();
            $table->unsignedInteger('likes_count')->default(0);         // denormalised
            $table->timestamps();

            $table->unique('slug');
            $table->index('status');
            $table->index('user_id');
            $table->index('primary_category_id');
            $table->index(['status', 'created_at'], 'projects_status_created');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('primary_category_id')->references('id')->on('project_categories')->nullOnDelete();
            $table->foreign('discussion_id')->references('id')->on('discussions')->nullOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('projects');
    },
];
