<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\DefinitionSerializer;
use ErnestDefoe\Projects\Model\ProjectField;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/projects/config/fields  +  PATCH …/{id} — create/update a custom field (admin). */
class SaveFieldController implements RequestHandlerInterface
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);
        $id = (int) Arr::get($request->getQueryParams(), 'id', 0);

        $field = $id ? ProjectField::query()->findOrFail($id) : new ProjectField();

        $name = trim((string) Arr::get($attrs, 'name', ''));
        if ($name === '') {
            throw new ValidationException(['name' => $this->translator->trans('ernestdefoe-projects.api.field_name_required')]);
        }
        $field->name = $name;

        $type = (string) Arr::get($attrs, 'type', 'text');
        $field->type = in_array($type, ProjectField::TYPES, true) ? $type : 'text';

        if (! $field->exists) {
            $field->key = self::uniqueKey(Str::slug($name, '_') ?: 'field');
        }

        $options = Arr::get($attrs, 'options', []);
        $field->options = $field->type === 'select'
            ? array_values(array_filter(array_map(fn ($o) => trim((string) $o), (array) $options), fn ($o) => $o !== ''))
            : null;

        $field->icon = trim((string) Arr::get($attrs, 'icon', '')) ?: null;
        $field->prefix = trim((string) Arr::get($attrs, 'prefix', '')) ?: null;
        $field->suffix = trim((string) Arr::get($attrs, 'suffix', '')) ?: null;
        $field->is_required = (bool) Arr::get($attrs, 'isRequired', false);
        $field->on_card = (bool) Arr::get($attrs, 'onCard', true);

        // Per-tag restriction: empty list => available for every category.
        $cats = array_values(array_filter(array_map('intval', (array) Arr::get($attrs, 'categoryIds', [])), fn ($i) => $i > 0));
        $field->category_ids = $cats ?: null;

        $field->position = (int) Arr::get($attrs, 'position', $field->position ?? 0);
        $field->save();

        return new JsonResponse(['data' => DefinitionSerializer::field($field)], $id ? 200 : 201);
    }

    private static function uniqueKey(string $base): string
    {
        $key = $base;
        $i = 2;
        while (ProjectField::query()->where('key', $key)->exists()) {
            $key = $base . '_' . $i++;
        }

        return $key;
    }
}
