<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Model\ProjectCategory;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** DELETE /api/projects/config/categories/{id} (admin). */
class DeleteCategoryController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $category = ProjectCategory::query()->findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
        $category->delete(); // pivot rows cascade; projects.primary_category_id nulls

        return new EmptyResponse(204);
    }
}
