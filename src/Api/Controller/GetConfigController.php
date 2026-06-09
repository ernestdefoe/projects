<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\DefinitionSerializer;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/projects/config — all definitions (admin only; drives the admin UI). */
class GetConfigController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        return new JsonResponse(['data' => DefinitionSerializer::all()]);
    }
}
