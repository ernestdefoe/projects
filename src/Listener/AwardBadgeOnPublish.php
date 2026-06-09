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

        $badgeId = (int) $this->settings->get('ernestdefoe-projects.publish_badge_id');
        $userId = $event->project->user_id;
        if (! $badgeId || ! $userId) {
            return;
        }

        try {
            /** @var \FoF\Badges\Badge|null $badge */
            $badge = \FoF\Badges\Badge::query()->find($badgeId);
            if (! $badge) {
                return;
            }

            // Award once — skip if the user already holds it.
            if ($badge->users()->where('users.id', $userId)->exists()) {
                return;
            }

            $badge->users()->attach($userId, ['assigned_at' => now()]);
        } catch (\Throwable $e) {
            // Never let a badges hiccup break project publishing.
            $this->log->warning('[projects] failed to award publish badge: ' . $e->getMessage());
        }
    }
}
