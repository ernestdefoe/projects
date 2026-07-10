<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Model\ProjectButton;
use ErnestDefoe\Projects\Model\ProjectCategory;
use ErnestDefoe\Projects\Model\ProjectField;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/projects/config/reorder — persist a new display order for tags,
 * parameters or button slots (admin). Body: { kind, ids: [orderedIds] }.
 *
 * A dedicated bulk endpoint rather than per-item PATCHes: the Save* controllers
 * require the full record (name, etc.) and would blank other columns on a
 * position-only update. Ordering is read back from `position` everywhere
 * (DefinitionSerializer + ProjectSerializer), so writing it here is enough to
 * reorder the form, cards and project pages.
 */
class ReorderDefinitionsController implements RequestHandlerInterface
{
    private const MODELS = [
        'categories' => ProjectCategory::class,
        'fields'     => ProjectField::class,
        'buttons'    => ProjectButton::class,
    ];

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);
        $kind = (string) Arr::get($attrs, 'kind', '');
        $ids = array_values(array_filter(array_map('intval', (array) Arr::get($attrs, 'ids', []))));

        $model = self::MODELS[$kind] ?? null;
        if (! $model) {
            return new JsonResponse(['errors' => [['status' => '422', 'detail' => 'Unknown kind']]], 422);
        }

        foreach ($ids as $index => $id) {
            $model::query()->where('id', $id)->update(['position' => $index]);
        }

        return new JsonResponse(null, 204);
    }
}
