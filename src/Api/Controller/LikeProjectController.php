<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\ProjectSerializer;
use ErnestDefoe\Projects\Model\Project;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/projects/{id}/like — toggle the actor's like on a project. */
class LikeProjectController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $id = (int) Arr::get($request->getQueryParams(), 'id');
        $project = Project::query()->whereVisibleTo($actor)->findOrFail($id);

        if ($project->likes()->where('users.id', $actor->id)->exists()) {
            $project->likes()->detach($actor->id);
        } else {
            $project->likes()->attach($actor->id, ['created_at' => now()]);
        }

        $project->likes_count = $project->likes()->count();
        $project->save();

        $project->load(['user', 'primaryCategory', 'categories', 'fieldValues.field', 'links.button', 'likes']);

        return new JsonResponse(['data' => ProjectSerializer::serialize($project, $actor)]);
    }
}
