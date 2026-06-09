<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Model\ProjectField;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** DELETE /api/projects/config/fields/{id} (admin). */
class DeleteFieldController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $field = ProjectField::query()->findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
        $field->delete(); // field values cascade

        return new EmptyResponse(204);
    }
}
