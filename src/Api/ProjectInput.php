<?php

namespace ErnestDefoe\Projects\Api;

use ErnestDefoe\Projects\Model\Project;
use ErnestDefoe\Projects\Model\ProjectAuthor;
use ErnestDefoe\Projects\Model\ProjectButton;
use ErnestDefoe\Projects\Model\ProjectField;
use ErnestDefoe\Projects\Model\ProjectFieldValue;
use ErnestDefoe\Projects\Model\ProjectLink;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
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

    public function __construct(
        private TranslatorInterface $translator,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    /** Translate an api.* error message in the actor's locale. */
    private function t(string $key, array $params = []): string
    {
        return $this->translator->trans('ernestdefoe-projects.api.' . $key, $params);
    }

    /** Apply scalar attributes to the (unsaved or existing) project. */
    public function apply(Project $project, array $attrs, bool $creating): void
    {
        $errors = [];

        if (array_key_exists('title', $attrs) || $creating) {
            $title = trim((string) Arr::get($attrs, 'title', ''));
            if ($title === '') {
                $errors['title'] = $this->t('title_required');
            } elseif (mb_strlen($title) > 255) {
                $errors['title'] = $this->t('title_max');
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
                $errors['excerpt'] = $this->t('excerpt_max', ['{max}' => self::EXCERPT_MAX]);
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
                $errors['image'] = $this->t('image_invalid');
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
    public function syncRelations(Project $project, array $attrs, User $actor): void
    {
        $errors = [];

        // ---- Categories ----------------------------------------------------
        if (array_key_exists('categoryIds', $attrs)) {
            $ids = collect((array) $attrs['categoryIds'])->map(fn ($i) => (int) $i)->filter()->unique()->values();

            $settings = $this->settings;
            $min = (int) $settings->get('ernestdefoe-projects.min_categories', 0);
            $max = (int) $settings->get('ernestdefoe-projects.max_categories', 0);

            if ($min > 0 && $ids->count() < $min) {
                $errors['categoryIds'] = $this->t('categories_min', ['{min}' => $min]);
            } elseif ($max > 0 && $ids->count() > $max) {
                $errors['categoryIds'] = $this->t('categories_max', ['{max}' => $max]);
            } else {
                $project->categories()->sync($ids->all());

                $primary = (int) Arr::get($attrs, 'primaryCategoryId', 0);
                $project->primary_category_id = ($primary && $ids->contains($primary))
                    ? $primary
                    : ($ids->first() ?: null);
                $project->save();
            }
        }

        // Categories this project belongs to — gate which fields/buttons apply
        // (per-tag restriction; an empty restriction list means "all").
        $projectCatIds = $project->categories()->get()->pluck('id')->map(fn ($i) => (int) $i)->all();

        // ---- Custom field values ------------------------------------------
        if (array_key_exists('fieldValues', $attrs)) {
            $fields = ProjectField::all()->keyBy('id');
            $given  = (array) $attrs['fieldValues']; // { fieldId: value }

            // Load every existing value row for this project up front (one query)
            // instead of querying per field — avoids the 2N+1 on save.
            $existingValues = ProjectFieldValue::query()
                ->where('project_id', $project->id)
                ->get()
                ->keyBy('field_id');

            foreach ($fields as $field) {
                // Skip fields not applicable to this project's categories; drop any
                // stale value a now-inapplicable field may have had.
                if (! self::appliesTo($field->category_ids, $projectCatIds)) {
                    $existingValues->get($field->id)?->delete();
                    continue;
                }

                $raw = Arr::get($given, (string) $field->id, Arr::get($given, $field->key));
                $value = $this->normaliseFieldValue($field, $raw, $errors);

                $existing = $existingValues->get($field->id);

                if ($value === null || $value === '') {
                    if ($field->is_required) {
                        $errors['field_' . $field->key] = $this->t('field_required', ['{field}' => $field->name]);
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
                    $errors['link_' . $position] = $this->t('link_invalid');
                    continue;
                }

                $buttonId = (int) Arr::get($entry, 'buttonId', 0);
                $button = $buttonId ? $buttons->get($buttonId) : null;
                if ($button && ! $button->allowsUrl($url)) {
                    $errors['link_' . $button->key] = $this->t('link_domain', ['{button}' => $button->label]);
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

            // Required buttons must be filled — but only those applicable to this
            // project's categories.
            foreach ($buttons as $button) {
                if ($button->is_required && empty($seenButtons[$button->id]) && self::appliesTo($button->category_ids, $projectCatIds)) {
                    $errors['link_' . $button->key] = $this->t('link_required', ['{button}' => $button->label]);
                }
            }
        }

        // ---- Co-authors -----------------------------------------------------
        // Each entry is a username (linked to that forum user) or a free-text
        // name. Display-only — co-authors get no edit rights.
        if (array_key_exists('coAuthors', $attrs)) {
            $project->coAuthors()->delete();

            $position = 0;
            foreach (array_slice(array_values((array) $attrs['coAuthors']), 0, 10) as $entry) {
                $name = trim((string) $entry);
                if ($name === '') {
                    continue;
                }

                $user = User::query()->where('username', $name)->first();
                ProjectAuthor::create([
                    'project_id' => $project->id,
                    'user_id'    => $user?->id,
                    'name'       => $user ? null : mb_substr($name, 0, 80),
                    'position'   => $position++,
                ]);
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }
    }

    /** A field/button applies if it has no category restriction, or one of its
     *  categories is among the project's categories. */
    private static function appliesTo($categoryIds, array $projectCatIds): bool
    {
        $cats = array_map('intval', (array) ($categoryIds ?? []));

        return empty($cats) || count(array_intersect($cats, $projectCatIds)) > 0;
    }

    private function normaliseFieldValue(ProjectField $field, $raw, array &$errors)
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
                    $errors['field_' . $field->key] = $this->t('field_number', ['{field}' => $field->name]);
                    return null;
                }
                return $raw;
            case 'date':
                $raw = trim((string) $raw);
                if ($raw === '') return null;
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                    $errors['field_' . $field->key] = $this->t('field_date', ['{field}' => $field->name]);
                    return null;
                }
                return $raw;
            case 'url':
                $raw = trim((string) $raw);
                if ($raw === '') return null;
                if (! self::isSafeUrl($raw)) {
                    $errors['field_' . $field->key] = $this->t('field_url', ['{field}' => $field->name]);
                    return null;
                }
                return $raw;
            case 'select':
                $raw = trim((string) $raw);
                if ($raw === '') return null;
                $options = array_map('strval', (array) ($field->options ?? []));
                if ($options && ! in_array($raw, $options, true)) {
                    $errors['field_' . $field->key] = $this->t('field_choice', ['{field}' => $field->name]);
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
