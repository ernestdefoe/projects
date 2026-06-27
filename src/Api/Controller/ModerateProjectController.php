<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\ProjectSerializer;
use ErnestDefoe\Projects\Event\ProjectWasPublished;
use ErnestDefoe\Projects\Model\Project;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/projects/{id}/moderate — approve or reject a pending project. */
class ModerateProjectController implements RequestHandlerInterface
{
    public function __construct(
        private Dispatcher $events,
        private ProjectSerializer $serializer,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('projects.moderate');

        $id = (int) Arr::get($request->getQueryParams(), 'id');
        $project = Project::query()->findOrFail($id);

        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);
        $action = (string) Arr::get($attrs, 'action', 'approve');
        $wasPublished = $project->isPublished();

        if ($action === 'reject') {
            $project->status = Project::STATUS_REJECTED;
            $project->rejection_reason = trim((string) Arr::get($attrs, 'reason', '')) ?: null;
        } else {
            $project->status = Project::STATUS_PUBLISHED;
            $project->rejection_reason = null;
        }

        $project->save();

        if (! $wasPublished && $project->isPublished()) {
            $this->events->dispatch(new ProjectWasPublished($project, $project->user ?? $actor));
        }

        $project->load(['user', 'primaryCategory', 'categories', 'fieldValues.field', 'links.button', 'likes', 'coAuthors.user']);

        return new JsonResponse(['data' => $this->serializer->serialize($project, $actor, true, $request)]);
    }
}
