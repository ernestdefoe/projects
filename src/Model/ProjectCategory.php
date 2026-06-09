<?php

namespace ErnestDefoe\Projects\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An admin-defined project category/tag with an icon + accent colour. The
 * "main" category of a project drives the badge shown next to its author's name.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string|null $color
 * @property string|null $description
 * @property int $position
 */
class ProjectCategory extends AbstractModel
{
    protected $table = 'project_categories';

    protected $casts = [
        'position'   => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'color' => '#5b3df5',
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_category', 'category_id', 'project_id');
    }
}
