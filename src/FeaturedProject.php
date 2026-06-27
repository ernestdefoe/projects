<?php

namespace ErnestDefoe\Projects;

use ErnestDefoe\Projects\Model\Project;
use Flarum\User\User;

/**
 * Maintains the denormalised "featured project" snapshot stored on the user
 * (users.projects_featured). Keeping a tiny JSON blob on the user means the
 * username badge can render with zero extra queries — no N+1 across post
 * streams. Recomputed whenever any of the user's projects is saved or deleted.
 */
class FeaturedProject
{
    public static function refresh(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $project = Project::query()
            ->where('user_id', $userId)
            ->where('status', Project::STATUS_PUBLISHED)
            ->with('primaryCategory')
            ->orderByDesc('is_featured') // the member's explicit pick wins…
            ->orderByDesc('created_at')  // …otherwise the latest published
            ->orderByDesc('id')
            ->first();

        $snapshot = null;
        if ($project) {
            $category = $project->primaryCategory;
            $snapshot = json_encode([
                'id'           => (int) $project->id,
                'title'        => $project->title,
                'slug'         => $project->slug,
                'icon'         => $category?->icon,
                'color'        => $category?->color,
                'categoryName' => $category?->name,
            ]);
        }

        // Avoid a redundant write (and the user-saved events it fires).
        if ($user->projects_featured !== $snapshot) {
            $user->projects_featured = $snapshot;
            $user->save();
        }
    }
}
