<?php

namespace ErnestDefoe\Projects\Api;

use ErnestDefoe\Projects\Event\ProjectWasPublished;
use ErnestDefoe\Projects\Model\Project;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Owns project create/update persistence: wraps the row insert + relation sync
 * in a transaction (so a validation failure rolls the whole thing back instead
 * of leaving an orphan) and fires the publish event. Keeps the controllers thin
 * and hides the ConnectionInterface / ProjectInput plumbing behind one method.
 */
class ProjectRepository
{
    public function __construct(
        private ConnectionInterface $db,
        private ProjectInput $input,
        private Dispatcher $events,
    ) {
    }

    public function create(array $attrs, User $actor, bool $canPublish): Project
    {
        $project = $this->db->transaction(function () use ($attrs, $actor, $canPublish) {
            $project = new Project();
            $project->user_id = $actor->id;
            $this->input->apply($project, $attrs, true);
            $project->status = $canPublish ? Project::STATUS_PUBLISHED : Project::STATUS_PENDING;

            // Retry on a slug collision (concurrent create of the same title).
            $baseSlug = $project->slug;
            for ($attempt = 0; ; $attempt++) {
                try {
                    $project->save();
                    break;
                } catch (UniqueConstraintViolationException $e) {
                    if ($attempt >= 3) {
                        throw $e;
                    }
                    $project->slug = $baseSlug . '-' . Str::lower(Str::random(5));
                }
            }

            $this->input->syncRelations($project, $attrs, $actor);

            return $project;
        });

        if ($project->isPublished()) {
            $this->events->dispatch(new ProjectWasPublished($project, $actor));
        }

        return $project;
    }

    public function update(Project $project, array $attrs, User $actor): Project
    {
        $wasPublished = $project->isPublished();

        $this->db->transaction(function () use ($project, $attrs, $actor) {
            $this->input->apply($project, $attrs, false);

            // Editing a declined project RESUBMITS it: back to pending review
            // (or straight to published for members who skip moderation).
            // Previously the edit saved but the project stayed rejected
            // forever — "the user can edit a declined project, but it doesn't
            // lead anywhere." Moderators editing someone's rejected project
            // don't trigger this; they have explicit approve/reject controls.
            $isModerator = $actor->isAdmin() || $actor->hasPermission('projects.moderate');
            if ($project->status === Project::STATUS_REJECTED && ! $isModerator) {
                $project->status = $actor->hasPermission('projects.skipModeration')
                    ? Project::STATUS_PUBLISHED
                    : Project::STATUS_PENDING;
                $project->rejection_reason = null;
            }

            $project->save();
            $this->input->syncRelations($project, $attrs, $actor);
        });

        if (! $wasPublished && $project->isPublished()) {
            $this->events->dispatch(new ProjectWasPublished($project, $actor));
        }

        return $project;
    }
}
