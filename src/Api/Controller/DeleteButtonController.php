<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Model\ProjectButton;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** DELETE /api/projects/config/buttons/{id} (admin). */
class DeleteButtonController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $button = ProjectButton::query()->findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
        $button->delete(); // links.button_id nulls

        return new EmptyResponse(204);
    }
}
