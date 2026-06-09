<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\DefinitionSerializer;
use ErnestDefoe\Projects\Model\ProjectCategory;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/projects/config/categories  +  PATCH …/{id} — create/update a category (admin). */
class SaveCategoryController implements RequestHandlerInterface
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);
        $id = (int) Arr::get($request->getQueryParams(), 'id', 0);

        $category = $id ? ProjectCategory::query()->findOrFail($id) : new ProjectCategory();

        $name = trim((string) Arr::get($attrs, 'name', ''));
        if ($name === '') {
            throw new ValidationException(['name' => $this->translator->trans('ernestdefoe-projects.api.category_name_required')]);
        }
        $category->name = $name;

        if (! $category->exists || Arr::get($attrs, 'slug')) {
            $category->slug = self::uniqueSlug(Str::slug(Arr::get($attrs, 'slug') ?: $name) ?: 'category', $category->id);
        }

        $category->icon = trim((string) Arr::get($attrs, 'icon', '')) ?: null;
        $category->color = trim((string) Arr::get($attrs, 'color', '')) ?: '#5b3df5';
        $category->description = trim((string) Arr::get($attrs, 'description', '')) ?: null;
        $category->position = (int) Arr::get($attrs, 'position', $category->position ?? 0);
        $category->save();

        return new JsonResponse(['data' => DefinitionSerializer::category($category)], $id ? 200 : 201);
    }

    private static function uniqueSlug(string $base, ?int $ignoreId): string
    {
        $slug = $base;
        $i = 2;
        while (ProjectCategory::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
