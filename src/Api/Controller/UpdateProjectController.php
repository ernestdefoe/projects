<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\ProjectInput;
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

/** PATCH /api/projects/{id} — update a project (author or moderator). */
class UpdateProjectController implements RequestHandlerInterface
{
    public function __construct(private Dispatcher $events)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = (int) Arr::get($request->getQueryParams(), 'id');

        $project = Project::query()->findOrFail($id);

        if (! ProjectSerializer::canEdit($project, $actor)) {
            $actor->assertPermission(false);
        }

        $wasPublished = $project->isPublished();
        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);

        ProjectInput::apply($project, $attrs, false);
        $project->save();
        ProjectInput::syncRelations($project, $attrs, $actor);

        if (! $wasPublished && $project->isPublished()) {
            $this->events->dispatch(new ProjectWasPublished($project, $actor));
        }

        $project->refresh()->load(['user', 'primaryCategory', 'categories', 'fieldValues.field', 'links.button', 'likes']);

        return new JsonResponse(['data' => ProjectSerializer::serialize($project, $actor, true, $request)]);
    }
}
