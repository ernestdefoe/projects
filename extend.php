<?php

/*
 * This file is part of ernestdefoe/projects.
 *
 * User Projects for Flarum 2 — a flexible showcase for creator communities.
 */

use ErnestDefoe\Projects\Api\Controller;
use ErnestDefoe\Projects\Api\DefinitionSerializer;
use ErnestDefoe\Projects\Event\ProjectWasPublished;
use ErnestDefoe\Projects\Listener\AwardBadgeOnPublish;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less')
        ->route('/projects', 'projects')
        ->route('/projects/p/{slug}', 'projects.show'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    // ---- JSON API (custom controllers) --------------------------------------
    (new Extend\Routes('api'))
        ->get('/projects', 'projects.list', Controller\ListProjectsController::class)
        ->post('/projects', 'projects.create', Controller\CreateProjectController::class)
        ->post('/projects/upload-image', 'projects.upload', Controller\UploadImageController::class)
        ->get('/projects/config', 'projects.config', Controller\GetConfigController::class)
        ->post('/projects/config/categories', 'projects.categories.create', Controller\SaveCategoryController::class)
        ->patch('/projects/config/categories/{id}', 'projects.categories.update', Controller\SaveCategoryController::class)
        ->delete('/projects/config/categories/{id}', 'projects.categories.delete', Controller\DeleteCategoryController::class)
        ->post('/projects/config/fields', 'projects.fields.create', Controller\SaveFieldController::class)
        ->patch('/projects/config/fields/{id}', 'projects.fields.update', Controller\SaveFieldController::class)
        ->delete('/projects/config/fields/{id}', 'projects.fields.delete', Controller\DeleteFieldController::class)
        ->post('/projects/config/buttons', 'projects.buttons.create', Controller\SaveButtonController::class)
        ->patch('/projects/config/buttons/{id}', 'projects.buttons.update', Controller\SaveButtonController::class)
        ->delete('/projects/config/buttons/{id}', 'projects.buttons.delete', Controller\DeleteButtonController::class)
        ->post('/projects/{id}/like', 'projects.like', Controller\LikeProjectController::class)
        ->post('/projects/{id}/moderate', 'projects.moderate', Controller\ModerateProjectController::class)
        ->get('/projects/{id}', 'projects.show', Controller\ShowProjectController::class)
        ->patch('/projects/{id}', 'projects.update', Controller\UpdateProjectController::class)
        ->delete('/projects/{id}', 'projects.delete', Controller\DeleteProjectController::class),

    // ---- Settings exposed to the forum --------------------------------------
    (new Extend\Settings())
        ->serializeToForum('projectsAllowAdhocLinks', 'ernestdefoe-projects.allow_adhoc_links', fn ($v) => (bool) $v)
        ->serializeToForum('projectsExcerptLimit', 'ernestdefoe-projects.excerpt_limit', fn ($v) => (int) ($v ?: 280)),

    // ---- Forum payload: permissions + the building-block definitions --------
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canCreateProject')
                ->get(fn ($model, Context $context) => $context->getActor()->can('projects.create')),
            Schema\Boolean::make('canModerateProjects')
                ->get(fn ($model, Context $context) => $context->getActor()->can('projects.moderate')),
            // The submission form + filters need the admin-defined categories,
            // custom fields and button slots — ship them in the boot payload so
            // the UI renders without an extra round-trip.
            Schema\Arr::make('projectsConfig')
                ->get(fn () => DefinitionSerializer::all()),
        ]),

    // ---- User payload: the featured-project snapshot (badge + profile) ------
    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Schema\Arr::make('projectFeatured')
                ->nullable()
                ->get(fn (User $user) => $user->projects_featured
                    ? json_decode($user->projects_featured, true)
                    : null),
        ]),

    // ---- Award a FoF Badge when a project is published (soft integration) ---
    (new Extend\Event())
        ->listen(ProjectWasPublished::class, AwardBadgeOnPublish::class),
];
