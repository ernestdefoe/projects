<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\ProjectRepository;
use ErnestDefoe\Projects\Api\ProjectSerializer;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/projects — create a project (requires projects.create). */
class CreateProjectController implements RequestHandlerInterface
{
    public function __construct(
        private ProjectRepository $repository,
        private ProjectSerializer $serializer,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $actor->assertCan('projects.create');

        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);

        $canPublish = $actor->isAdmin()
            || $actor->hasPermission('projects.moderate')
            || $actor->hasPermission('projects.skipModeration');

        $project = $this->repository->create($attrs, $actor, $canPublish);

        $project->refresh()->load(['user', 'primaryCategory', 'categories', 'fieldValues.field', 'links.button', 'likes', 'coAuthors.user']);

        return new JsonResponse(['data' => $this->serializer->serialize($project, $actor, true, $request)], 201);
    }
}
