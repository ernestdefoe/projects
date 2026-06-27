<?php

namespace ErnestDefoe\Projects\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A co-author of a project: either a linked forum user (user_id) or a plain
 * text name. Display-only.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $user_id
 * @property string|null $name
 * @property int $position
 */
class ProjectAuthor extends AbstractModel
{
    protected $table = 'project_authors';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
