<?php

namespace ErnestDefoe\Projects\Api;

use ErnestDefoe\Projects\Model\Project;
use ErnestDefoe\Projects\Model\ProjectButton;
use ErnestDefoe\Projects\Model\ProjectField;
use ErnestDefoe\Projects\Model\ProjectFieldValue;
use ErnestDefoe\Projects\Model\ProjectLink;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Applies request attributes to a Project and syncs its categories, custom
 * field values and links — validating required fields, select choices, URL
 * schemes and per-button domain allow-lists along the way.
 */
class ProjectInput
{
    private const EXCERPT_MAX = 280;

    /** Apply scalar attributes to the (unsaved or existing) project. */
    public static function apply(Project $project, array $attrs, bool $creating): void
    {
        $errors = [];

        if (array_key_exists('title', $attrs) || $creating) {
            $title = trim((string) Arr::get($attrs, 'title', ''));
            if ($title === '') {
                $errors['title'] = 'The title is required.';
            } elseif (mb_strlen($title) > 255) {
                $errors['title'] = 'The title must be at most 255 characters.';
            } else {
                $project->title = $title;
                if ($creating || empty($project->slug)) {
                    $project->slug = self::uniqueSlugBase($title);
                }
            }
        }

        if (array_key_exists('excerpt', $attrs)) {
            $excerpt = trim((string) $attrs['excerpt']);
            if (mb_strlen($excerpt) > self::EXCERPT_MAX) {
                $errors['excerpt'] = 'The short description must be at most ' . self::EXCERPT_MAX . ' characters.';
            }
            $project->excerpt = $excerpt !== '' ? $excerpt : null;
        }

        if (array_key_exists('content', $attrs)) {
            $content = trim((string) $attrs['content']);
            $project->content = $content !== '' ? $content : null;
        }

        if (array_key_exists('image', $attrs)) {
            $image = trim((string) $attrs['image']);
            if ($image !== '' && ! self::isSafeUrl($image)) {
                $errors['image'] = 'The image URL is not valid.';
            }
            $project->image_path = $image !== '' ? $image : null;
        }

        if (array_key_exists('discussionId', $attrs)) {
            $did = $attrs['discussionId'];
            $project->discussion_id = ($did === null || $did === '') ? null : (int) $did;
        }

        if ($errors) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Sync categories (+ primary), custom field values and links. Validates
     * required fields/buttons, select options, URL safety and domain rules.
     * Call only AFTER the project has an id (saved).
     */
    public static function syncRelations(Project $project, array $attrs, User $actor): void
    {
        $errors = [];

        // ---- Categories ----------------------------------------------------
        if (array_key_exists('categoryIds', $attrs)) {
            $ids = collect((array) $attrs['categoryIds'])->map(fn ($i) => (int) $i)->filter()->unique()->values();
            $project->categories()->sync($ids->all());

            $primary = (int) Arr::get($attrs, 'primaryCategoryId', 0);
            $project->primary_category_id = ($primary && $ids->contains($primary))
                ? $primary
                : ($ids->first() ?: null);
            $project->save();
        }

        // ---- Custom field values ------------------------------------------
        if (array_key_exists('fieldValues', $attrs)) {
            $fields = ProjectField::all()->keyBy('id');
            $given  = (array) $attrs['fieldValues']; // { fieldId: value }

            foreach ($fields as $field) {
                $raw = Arr::get($given, (string) $field->id, Arr::get($given, $field->key));
                $value = self::normaliseFieldValue($field, $raw, $errors);

                $existing = ProjectFieldValue::query()
                    ->where('project_id', $project->id)
                    ->where('field_id', $field->id)
                    ->first();

                if ($value === null || $value === '') {
                    if ($field->is_required) {
                        $errors['field_' . $field->key] = $field->name . ' is required.';
                    }
                    $existing?->delete();
                    continue;
                }

                $row = $existing ?: new ProjectFieldValue(['project_id' => $project->id, 'field_id' => $field->id]);
                $row->value = (string) $value;
                $row->save();
            }
        }

        // ---- Links ---------------------------------------------------------
        if (array_key_exists('links', $attrs)) {
            $buttons = ProjectButton::all()->keyBy('id');
            $given   = array_values((array) $attrs['links']);
            $seenButtons = [];

            $project->links()->delete();
            $position = 0;
            foreach ($given as $entry) {
                $url = trim((string) Arr::get($entry, 'url', ''));
                if ($url === '') {
                    continue;
                }
                if (! self::isSafeUrl($url)) {
                    $errors['link_' . $position] = 'One of the links is not a valid URL.';
                    continue;
                }

                $buttonId = (int) Arr::get($entry, 'buttonId', 0);
                $button = $buttonId ? $buttons->get($buttonId) : null;
                if ($button && ! $button->allowsUrl($url)) {
                    $errors['link_' . $button->key] = 'The ' . $button->label . ' link must point to an allowed domain.';
                    continue;
                }

                $label = trim((string) Arr::get($entry, 'label', ''));
                if ($button && ! $button->allow_custom_label) {
                    $label = '';
                }

                ProjectLink::create([
                    'project_id' => $project->id,
                    'button_id'  => $button?->id,
                    'url'        => $url,
                    'label'      => $label !== '' ? $label : null,
                    'position'   => $position++,
                ]);

                if ($button) {
                    $seenButtons[$button->id] = true;
                }
            }

            // Required buttons must be filled.
            foreach ($buttons as $button) {
                if ($button->is_required && empty($seenButtons[$button->id])) {
                    $errors['link_' . $button->key] = 'The ' . $button->label . ' link is required.';
                }
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }
    }

    private static function normaliseFieldValue(ProjectField $field, $raw, array &$errors)
    {
        if ($raw === null) {
            return null;
        }

        switch ($field->type) {
            case 'boolean':
                return filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? '1' : null;
            case 'number':
                $raw = trim((string) $raw);
                if ($raw === '') return null;
                if (! is_numeric($raw)) {
                    $errors['field_' . $field->key] = $field->name . ' must be a number.';
                    return null;
                }
                return $raw;
            case 'date':
                $raw = trim((string) $raw);
                if ($raw === '') return null;
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                    $errors['field_' . $field->key] = $field->name . ' must be a valid date.';
                    return null;
                }
                return $raw;
            case 'url':
                $raw = trim((string) $raw);
                if ($raw === '') return null;
                if (! self::isSafeUrl($raw)) {
                    $errors['field_' . $field->key] = $field->name . ' must be a valid URL.';
                    return null;
                }
                return $raw;
            case 'select':
                $raw = trim((string) $raw);
                if ($raw === '') return null;
                $options = array_map('strval', (array) ($field->options ?? []));
                if ($options && ! in_array($raw, $options, true)) {
                    $errors['field_' . $field->key] = $field->name . ' has an invalid choice.';
                    return null;
                }
                return $raw;
            default: // text, textarea
                $raw = trim((string) $raw);
                return $raw !== '' ? mb_substr($raw, 0, 2000) : null;
        }
    }

    /** http/https absolute URLs or root-relative paths only (blocks javascript:/data:). */
    public static function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        // Root-relative ("/foo") allowed, but not protocol-relative ("//evil").
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private static function uniqueSlugBase(string $title): string
    {
        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'project';
        }

        return $slug;
    }
}
