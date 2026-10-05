<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\DefinitionSerializer;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/projects/config — admins get every definition plus the badge list
 * (drives the admin UI); everyone else gets the cached forum subset the
 * projects page and submission form render from.
 */
class GetConfigController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $admin = RequestUtil::getActor($request)->isAdmin();

        return new JsonResponse(['data' => $admin ? DefinitionSerializer::all() : DefinitionSerializer::cached()]);
    }
}
