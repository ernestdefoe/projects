<?php

namespace ErnestDefoe\Projects;

use ErnestDefoe\Projects\Model\Project;
use Flarum\User\User;
use Illuminate\Database\Connection;

/**
 * Maintains the denormalised "featured project" snapshot stored on the user
 * (users.projects_featured). Keeping a tiny JSON blob on the user means the
 * username badge can render with zero extra queries — no N+1 across post
 * streams. Recomputed whenever any of the user's projects is saved or deleted.
 */
class FeaturedProject
{
    /** @var array<int, true> users with a refresh already waiting on the commit */
    private static array $pending = [];

    /**
     * Refresh once the surrounding transaction commits. A create or edit saves
     * the project twice (the row, then its primary tag after the tag sync), and
     * each save fires the model hook; queueing collapses those into a single
     * refresh that sees the final state. Outside a transaction it runs at once.
     */
    public static function queue(?int $userId, Connection $db): void
    {
        if (! $userId) {
            return;
        }

        if ($db->transactionLevel() === 0) {
            unset(self::$pending[$userId]); // nothing can still be waiting
            self::refresh($userId);

            return;
        }

        if (isset(self::$pending[$userId])) {
            return;
        }

        self::$pending[$userId] = true;
        $db->afterCommit(function () use ($userId) {
            unset(self::$pending[$userId]);
            self::refresh($userId);
        });
        $db->afterRollBack(function () use ($userId) {
            unset(self::$pending[$userId]);
        });
    }

    public static function refresh(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        $current = User::query()->whereKey($userId)->first(['id', 'projects_featured']);
        if (! $current) {
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
                'id' => (int) $project->id,
                'title' => $project->title,
                'slug' => $project->slug,
                'icon' => $category?->icon,
                'color' => $category?->color,
                'categoryName' => $category?->name,
            ]);
        }

        // A targeted column update, and only when it changed: the snapshot is a
        // denormalised cache, so saving the whole User model (and firing every
        // extension's user-saved listeners) would be wasted work.
        if ($current->getAttribute('projects_featured') !== $snapshot) {
            User::query()->whereKey($userId)->update(['projects_featured' => $snapshot]);
        }
    }
}
