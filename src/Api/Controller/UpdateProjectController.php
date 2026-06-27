<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\ProjectRepository;
use ErnestDefoe\Projects\Api\ProjectSerializer;
use ErnestDefoe\Projects\Model\Project;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** PATCH /api/projects/{id} — update a project (author or moderator). */
class UpdateProjectController implements RequestHandlerInterface
{
    public function __construct(
        private ProjectRepository $repository,
        private ProjectSerializer $serializer,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = (int) Arr::get($request->getQueryParams(), 'id');

        $project = Project::query()->findOrFail($id);

        if (! ProjectSerializer::canEdit($project, $actor)) {
            $actor->assertPermission(false);
        }

        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);

        $project = $this->repository->update($project, $attrs, $actor);

        $project->refresh()->load(['user', 'primaryCategory', 'categories', 'fieldValues.field', 'links.button', 'likes', 'coAuthors.user']);

        return new JsonResponse(['data' => $this->serializer->serialize($project, $actor, true, $request)]);
    }
}
