<?php

namespace ErnestDefoe\Projects\Listener;

use ErnestDefoe\Projects\Event\ProjectWasPublished;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Awards a configured FoF Badge to the author when their project is published.
 *
 * Soft integration: does nothing unless fof/badges is installed AND an admin
 * has picked a badge in the Projects settings. Awards at most once per user
 * (skips if they already hold the badge), so it effectively rewards a member's
 * first published project.
 */
class AwardBadgeOnPublish
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private LoggerInterface $log,
    ) {
    }

    public function handle(ProjectWasPublished $event): void
    {
        // fof/badges not installed → nothing to do.
        if (! class_exists(\FoF\Badges\Badge::class)) {
            return;
        }

        $userId = $event->project->user_id;
        if (! $userId) {
            return;
        }

        // Collect the badges to grant: the global "any publish" badge, plus any
        // per-category badge for the categories this project belongs to (e.g.
        // Book -> Writer, Game -> Developer).
        $badgeIds = [(int) $this->settings->get('ernestdefoe-projects.publish_badge_id')];

        foreach ($event->project->categories()->get() as $category) {
            $badgeIds[] = (int) $category->badge_id;
        }

        foreach (array_unique(array_filter($badgeIds)) as $badgeId) {
            $this->award($badgeId, (int) $userId);
        }
    }

    /** Grant one badge to the user, once. Never lets a badges hiccup break publishing. */
    private function award(int $badgeId, int $userId): void
    {
        try {
            /** @var \FoF\Badges\Badge|null $badge */
            $badge = \FoF\Badges\Badge::query()->find($badgeId);
            if (! $badge) {
                return;
            }

            // Prefer fof/badges' own awarder: it skips duplicates, runs the
            // badge's actions (e.g. add-to-group), fires BadgeAwarded, AND
            // sends the "you earned a badge" notification — which the raw pivot
            // attach never did (the "users don't get notified" report).
            if (class_exists(\FoF\Badges\BadgeAwarder::class)) {
                $user = \Flarum\User\User::find($userId);
                if ($user) {
                    resolve(\FoF\Badges\BadgeAwarder::class)->award($user, $badge);
                }

                return;
            }

            // Older fof/badges without BadgeAwarder: attach directly (no
            // notification, but the award still lands). Pivot columns are
            // earned_at/granted_by/reason.
            if ($badge->users()->where('users.id', $userId)->exists()) {
                return;
            }
            $badge->users()->attach($userId, ['earned_at' => \Carbon\Carbon::now()]);
        } catch (\Throwable $e) {
            $this->log->warning('[projects] failed to award badge ' . $badgeId . ': ' . $e->getMessage());
        }
    }
}
