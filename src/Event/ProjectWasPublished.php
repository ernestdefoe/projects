<?php

namespace ErnestDefoe\Projects\Event;

use ErnestDefoe\Projects\Model\Project;
use Flarum\User\User;

/** Fired when a project transitions into the published state. */
class ProjectWasPublished
{
    public function __construct(
        public Project $project,
        public ?User $actor = null,
    ) {
    }
}
