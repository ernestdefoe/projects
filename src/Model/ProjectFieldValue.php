<?php

namespace ErnestDefoe\Projects\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single custom-parameter value for one project.
 *
 * @property int $id
 * @property int $project_id
 * @property int $field_id
 * @property string|null $value
 */
class ProjectFieldValue extends AbstractModel
{
    protected $table = 'project_field_values';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ProjectField::class, 'field_id');
    }
}
