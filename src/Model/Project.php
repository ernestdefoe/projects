<?php

namespace ErnestDefoe\Projects\Model;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user project — a showcase entry (a book, a game, an app, …) authored by a
 * forum user, optionally linked to a discussion for comments.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $primary_category_id
 * @property int|null $discussion_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $content
 * @property string|null $image_path
 * @property string $status
 * @property string|null $rejection_reason
 * @property int $likes_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Project extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'projects';

    protected $casts = [
        'likes_count' => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    protected $attributes = [
        'status'      => self::STATUS_PENDING,
        'likes_count' => 0,
    ];

    /**
     * Keep the author's featured-project snapshot (users.projects_featured) in
     * sync whenever any of their projects is saved or deleted — so the username
     * badge stays correct without per-request queries.
     */
    protected static function booted(): void
    {
        static::saved(fn (Project $project) => \ErnestDefoe\Projects\FeaturedProject::refresh($project->user_id));
        static::deleted(fn (Project $project) => \ErnestDefoe\Projects\FeaturedProject::refresh($project->user_id));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'primary_category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProjectCategory::class, 'project_category', 'project_id', 'category_id');
    }

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'discussion_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ProjectFieldValue::class, 'project_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProjectLink::class, 'project_id');
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_likes', 'project_id', 'user_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Restrict to projects the given actor may see: published ones for everyone,
     * plus the actor's own (any status) and everything when they can moderate.
     */
    public function scopeWhereVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasPermission('projects.moderate') || $actor->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($actor) {
            $q->where('status', self::STATUS_PUBLISHED);
            if (! $actor->isGuest()) {
                $q->orWhere('user_id', $actor->id);
            }
        });
    }
}
