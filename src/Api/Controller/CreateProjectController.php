<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\ProjectInput;
use ErnestDefoe\Projects\Api\ProjectSerializer;
use ErnestDefoe\Projects\Event\ProjectWasPublished;
use ErnestDefoe\Projects\Model\Project;
use Illuminate\Contracts\Events\Dispatcher;
use Flarum\Http\RequestUtil;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/projects — create a project (requires projects.create). */
class CreateProjectController implements RequestHandlerInterface
{
    public function __construct(private Dispatcher $events)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $actor->assertCan('projects.create');

        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);

        $project = new Project();
        $project->user_id = $actor->id;
        ProjectInput::apply($project, $attrs, true);

        $canPublish = $actor->isAdmin()
            || $actor->hasPermission('projects.moderate')
            || $actor->hasPermission('projects.skipModeration');
        $project->status = $canPublish ? Project::STATUS_PUBLISHED : Project::STATUS_PENDING;

        $baseSlug = $project->slug;
        for ($attempt = 0; ; $attempt++) {
            try {
                $project->save();
                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
                $project->slug = $baseSlug . '-' . Str::lower(Str::random(5));
            }
        }

        ProjectInput::syncRelations($project, $attrs, $actor);

        if ($project->isPublished()) {
            $this->events->dispatch(new ProjectWasPublished($project, $actor));
        }

        $project->refresh()->load(['user', 'primaryCategory', 'categories', 'fieldValues.field', 'links.button', 'likes']);

        return new JsonResponse(['data' => ProjectSerializer::serialize($project, $actor, true, $request)], 201);
    }
}
