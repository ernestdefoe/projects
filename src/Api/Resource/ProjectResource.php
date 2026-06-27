<?php

namespace ErnestDefoe\Projects\Api\Resource;

use ErnestDefoe\Projects\Model\Project;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * JSON:API resource for projects (type `projects`).
 *
 * Type-only registration (no endpoints): the forum talks to the custom plain-JSON
 * controllers at /api/projects, consistent with this extension's architecture
 * (and giveaways/roleplay). Registering the type makes projects observable and
 * extendable by other extensions and safe to expose as a relationship (e.g. from
 * a discussion or user). Adding endpoints here would collide with the custom routes.
 */
class ProjectResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'projects';
    }

    public function model(): string
    {
        return Project::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        // Same visibility rule the controllers enforce: published for everyone,
        // plus the actor's own and everything for moderators.
        $query->whereVisibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title'),
            Schema\Str::make('slug'),
            Schema\Str::make('excerpt')->nullable(),
            Schema\Str::make('image')->property('image_path')->nullable(),
            Schema\Str::make('status'),
            Schema\Integer::make('likesCount')->property('likes_count'),
            Schema\DateTime::make('createdAt')->property('created_at')->nullable(),
            Schema\DateTime::make('updatedAt')->property('updated_at')->nullable(),
        ];
    }
}
