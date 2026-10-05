<?php

namespace ErnestDefoe\Projects\Api;

use ErnestDefoe\Projects\Model\ProjectButton;
use ErnestDefoe\Projects\Model\ProjectCategory;
use ErnestDefoe\Projects\Model\ProjectField;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Serialises the admin-defined building blocks (categories, custom fields,
 * button slots). Used by the admin config endpoint and pushed into the forum
 * payload so the submission form + filters can render without extra requests.
 */
class DefinitionSerializer
{
    public const CACHE_KEY = 'ernestdefoe-projects.definitions';

    /**
     * What the forum needs (categories, fields, buttons — not the admin's
     * badge list), cached: the projects page and the submission form fetch it
     * from GET /api/projects/config. It used to ride in the forum payload of
     * every page on the site. Forgotten by every controller that writes a
     * category, field or button (and by reorder).
     */
    public static function cached(): array
    {
        $forum = fn () => ['categories' => self::categories(), 'fields' => self::fields(), 'buttons' => self::buttons()];

        try {
            return resolve(Cache::class)->remember(self::CACHE_KEY, 600, $forum);
        } catch (\Throwable $e) {
            return $forum();
        }
    }

    public static function forget(): void
    {
        try {
            resolve(Cache::class)->forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // A cache that cannot be cleared expires on its own within the TTL.
        }
    }

    public static function all(): array
    {
        return [
            'categories' => self::categories(),
            'fields'     => self::fields(),
            'buttons'    => self::buttons(),
            'badges'     => self::badges(),
        ];
    }

    /**
     * The available FoF badges (id + name), so the admin picks a badge from a
     * list instead of hunting for a numeric ID nothing surfaces. Empty when
     * fof/badges isn't installed. (Badge names are public in fof/badges, so
     * shipping them in the shared config leaks nothing.)
     */
    public static function badges(): array
    {
        if (! class_exists(\FoF\Badges\Badge::class)) {
            return [];
        }

        try {
            return \FoF\Badges\Badge::query()->orderBy('name')->get()
                ->map(fn ($b) => ['id' => (int) $b->id, 'name' => (string) $b->name])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function categories(): array
    {
        return ProjectCategory::query()->orderBy('position')->orderBy('name')->get()
            ->map(fn (ProjectCategory $c) => self::category($c))->all();
    }

    public static function fields(): array
    {
        return ProjectField::query()->orderBy('position')->orderBy('name')->get()
            ->map(fn (ProjectField $f) => self::field($f))->all();
    }

    public static function buttons(): array
    {
        return ProjectButton::query()->orderBy('position')->orderBy('label')->get()
            ->map(fn (ProjectButton $b) => self::button($b))->all();
    }

    public static function category(ProjectCategory $c): array
    {
        return [
            'id'          => (int) $c->id,
            'name'        => $c->name,
            'slug'        => $c->slug,
            'icon'        => $c->icon,
            'color'       => $c->color,
            'description' => $c->description,
            'badgeId'     => $c->badge_id ? (int) $c->badge_id : null,
            'position'    => (int) $c->position,
        ];
    }

    public static function field(ProjectField $f): array
    {
        return [
            'id'         => (int) $f->id,
            'name'       => $f->name,
            'description' => $f->description,
            'key'        => $f->key,
            'type'       => $f->type,
            'options'    => array_values((array) ($f->options ?? [])),
            'icon'       => $f->icon,
            'prefix'     => $f->prefix,
            'suffix'     => $f->suffix,
            'isRequired' => (bool) $f->is_required,
            'onCard'     => (bool) $f->on_card,
            'categoryIds' => array_values(array_map('intval', (array) ($f->category_ids ?? []))),
            'position'   => (int) $f->position,
        ];
    }

    public static function button(ProjectButton $b): array
    {
        return [
            'id'               => (int) $b->id,
            'label'            => $b->label,
            'key'              => $b->key,
            'icon'             => $b->icon,
            'allowedDomains'   => array_values((array) ($b->allowed_domains ?? [])),
            'allowCustomLabel' => (bool) $b->allow_custom_label,
            'isRequired'       => (bool) $b->is_required,
            'isPrimary'        => (bool) $b->is_primary,
            'categoryIds'      => array_values(array_map('intval', (array) ($b->category_ids ?? []))),
            'position'         => (int) $b->position,
        ];
    }
}
