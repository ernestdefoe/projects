<?php

namespace ErnestDefoe\Projects\Api;

use ErnestDefoe\Projects\Model\ProjectButton;
use ErnestDefoe\Projects\Model\ProjectCategory;
use ErnestDefoe\Projects\Model\ProjectField;

/**
 * Serialises the admin-defined building blocks (categories, custom fields,
 * button slots). Used by the admin config endpoint and pushed into the forum
 * payload so the submission form + filters can render without extra requests.
 */
class DefinitionSerializer
{
    public static function all(): array
    {
        return [
            'categories' => self::categories(),
            'fields'     => self::fields(),
            'buttons'    => self::buttons(),
        ];
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
            'position'    => (int) $c->position,
        ];
    }

    public static function field(ProjectField $f): array
    {
        return [
            'id'         => (int) $f->id,
            'name'       => $f->name,
            'key'        => $f->key,
            'type'       => $f->type,
            'options'    => array_values((array) ($f->options ?? [])),
            'icon'       => $f->icon,
            'prefix'     => $f->prefix,
            'suffix'     => $f->suffix,
            'isRequired' => (bool) $f->is_required,
            'onCard'     => (bool) $f->on_card,
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
            'position'         => (int) $b->position,
        ];
    }
}
