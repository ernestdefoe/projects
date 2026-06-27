<?php

namespace ErnestDefoe\Projects\Api;

use ErnestDefoe\Projects\Model\Project;
use Flarum\Formatter\Formatter;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Serialises a Project to a plain array for the extension's JSON endpoints.
 * One place builds the payload so the projects page, the detail view and the
 * profile tab all render identically.
 *
 * Pass $full = true on the detail endpoint to include the rendered content
 * HTML (forum formatting); the card/list endpoints omit it for weight.
 */
class ProjectSerializer
{
    public function __construct(private Formatter $formatter)
    {
    }

    public function serialize(Project $project, ?User $actor = null, bool $full = false, ?ServerRequestInterface $request = null): array
    {
        $author = null;
        if ($project->relationLoaded('user') && $project->user) {
            $author = [
                'id'          => (int) $project->user->id,
                'username'    => $project->user->username,
                'displayName' => $project->user->display_name ?: $project->user->username,
                'avatarUrl'   => $project->user->avatar_url,
                'slug'        => $project->user->slug ?? (string) $project->user->id,
            ];
        }

        $data = [
            'id'              => (int) $project->id,
            'title'           => $project->title,
            'slug'            => $project->slug,
            'excerpt'         => $project->excerpt,
            'image'           => $project->image_path,
            'status'          => $project->status,
            'rejectionReason' => $project->rejection_reason,
            'likesCount'      => (int) $project->likes_count,
            'liked'           => self::liked($project, $actor),
            'createdAt'       => optional($project->created_at)->toIso8601String(),
            'updatedAt'       => optional($project->updated_at)->toIso8601String(),
            'author'          => $author,
            'coAuthors'       => self::coAuthors($project),
            'primaryCategory' => self::category($project->relationLoaded('primaryCategory') ? $project->primaryCategory : null),
            'categories'      => $project->relationLoaded('categories')
                ? $project->categories->map(fn ($c) => self::category($c))->filter()->values()->all()
                : [],
            'fields'          => self::fields($project),
            'links'           => self::links($project),
            'discussionId'    => $project->discussion_id ? (int) $project->discussion_id : null,
            'canEdit'         => self::canEdit($project, $actor),
            'canDelete'       => self::canDelete($project, $actor),
            'canModerate'     => $actor && ! $actor->isGuest()
                && ($actor->isAdmin() || $actor->hasPermission('projects.moderate')),
            'isFeatured'      => (bool) $project->is_featured,
            'canFeature'      => self::canFeature($project, $actor),
        ];

        if ($full) {
            $data['contentHtml'] = $this->renderContent($project, $request);
            $data['content'] = $project->content; // raw, for the edit form
        }

        return $data;
    }

    /** Co-authors: linked forum users (with avatar/link) or plain text names. */
    private static function coAuthors(Project $project): array
    {
        if (! $project->relationLoaded('coAuthors')) {
            return [];
        }

        return $project->coAuthors
            ->sortBy('position')
            ->map(function ($a) {
                if ($a->user_id && $a->relationLoaded('user') && $a->user) {
                    $u = $a->user;

                    return [
                        'userId'      => (int) $u->id,
                        'username'    => $u->username,
                        'displayName' => $u->display_name ?: $u->username,
                        'avatarUrl'   => $u->avatar_url,
                        'slug'        => $u->slug ?? (string) $u->id,
                    ];
                }

                return ['name' => $a->name];
            })
            ->filter(fn ($a) => ! empty($a['name']) || ! empty($a['username']))
            ->values()
            ->all();
    }

    private static function category($category): ?array
    {
        if (! $category) {
            return null;
        }

        return [
            'id'    => (int) $category->id,
            'name'  => $category->name,
            'slug'  => $category->slug,
            'icon'  => $category->icon,
            'color' => $category->color,
        ];
    }

    private static function fields(Project $project): array
    {
        if (! $project->relationLoaded('fieldValues')) {
            return [];
        }

        return $project->fieldValues
            ->filter(fn ($v) => $v->relationLoaded('field') && $v->field !== null && $v->value !== null && $v->value !== '')
            ->sortBy(fn ($v) => $v->field->position)
            ->map(fn ($v) => [
                'id'     => (int) $v->field->id,
                'key'    => $v->field->key,
                'name'   => $v->field->name,
                'type'   => $v->field->type,
                'icon'   => $v->field->icon,
                'prefix' => $v->field->prefix,
                'suffix' => $v->field->suffix,
                'onCard' => (bool) $v->field->on_card,
                'value'  => $v->value,
            ])
            ->values()
            ->all();
    }

    private static function links(Project $project): array
    {
        if (! $project->relationLoaded('links')) {
            return [];
        }

        return $project->links
            ->sortBy('position')
            ->map(function ($link) {
                $button = $link->relationLoaded('button') ? $link->button : null;

                return [
                    'id'        => (int) $link->id,
                    'buttonId'  => $link->button_id ? (int) $link->button_id : null,
                    'url'       => $link->url,
                    'label'     => $link->label ?: ($button ? $button->label : $link->url),
                    'icon'      => $button ? $button->icon : null,
                    'isPrimary' => $button ? (bool) $button->is_primary : false,
                ];
            })
            ->values()
            ->all();
    }

    private function renderContent(Project $project, ?ServerRequestInterface $request): string
    {
        $content = (string) $project->content;
        if ($content === '') {
            return '';
        }

        try {
            $xml = $this->formatter->parse($content, $project);

            return $this->formatter->render($xml, $project, $request);
        } catch (\Throwable $e) {
            // Defensive fallback — never let a formatting hiccup 500 the detail page.
            return '<p>' . nl2br(htmlspecialchars($content, ENT_QUOTES)) . '</p>';
        }
    }

    private static function liked(Project $project, ?User $actor): ?bool
    {
        if (! $actor || $actor->isGuest()) {
            return null;
        }
        if ($project->relationLoaded('likes')) {
            return $project->likes->contains('id', $actor->id);
        }

        return null;
    }

    public static function canEdit(Project $project, ?User $actor): bool
    {
        if (! $actor || $actor->isGuest()) {
            return false;
        }
        if ($actor->isAdmin() || $actor->hasPermission('projects.moderate')) {
            return true;
        }

        return $project->user_id
            && (int) $actor->id === (int) $project->user_id
            && $actor->hasPermission('projects.create');
    }

    public static function canDelete(Project $project, ?User $actor): bool
    {
        // Same rule as edit for v1.
        return self::canEdit($project, $actor);
    }

    /** The owner may feature their own published project on their profile. */
    public static function canFeature(Project $project, ?User $actor): bool
    {
        return $actor && ! $actor->isGuest()
            && $project->user_id && (int) $actor->id === (int) $project->user_id
            && $project->isPublished();
    }
}
