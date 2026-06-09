<?php

namespace ErnestDefoe\Projects\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single filled-in link on a project, optionally tied to a button slot.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $button_id
 * @property string $url
 * @property string|null $label
 * @property int $position
 */
class ProjectLink extends AbstractModel
{
    protected $table = 'project_links';

    protected $guarded = [];

    protected $casts = [
        'position'   => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function button(): BelongsTo
    {
        return $this->belongsTo(ProjectButton::class, 'button_id');
    }
}
