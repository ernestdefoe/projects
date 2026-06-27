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
    public function __construct(private ProjectSerializer $serializer)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $id = (int) Arr::get($request->getQueryParams(), 'id');
        $project = Project::query()->whereVisibleTo($actor)->findOrFail($id);

        // Toggle the pivot and adjust the denormalised count atomically. Using a
        // query-builder increment/decrement (rather than reading count() and
        // calling $project->save()) keeps the count correct under concurrent
        // like/unlike and avoids firing the model's saved hook — a like must not
        // recompute the author's featured-project snapshot.
        if ($project->likes()->where('users.id', $actor->id)->exists()) {
            $project->likes()->detach($actor->id);
            Project::query()->whereKey($project->id)->where('likes_count', '>', 0)->decrement('likes_count');
        } else {
            $project->likes()->attach($actor->id, ['created_at' => \Carbon\Carbon::now()]);
            Project::query()->whereKey($project->id)->increment('likes_count');
        }

        $project->refresh()->load(['user', 'primaryCategory', 'categories', 'fieldValues.field', 'links.button', 'likes', 'coAuthors.user']);

        return new JsonResponse(['data' => $this->serializer->serialize($project, $actor)]);
    }
}
