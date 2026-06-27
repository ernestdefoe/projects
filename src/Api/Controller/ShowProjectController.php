<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\ProjectSerializer;
use ErnestDefoe\Projects\Model\Project;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/projects/{id} — a single project by id or slug, with full content. */
class ShowProjectController implements RequestHandlerInterface
{
    public function __construct(private ProjectSerializer $serializer)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $key = (string) Arr::get($request->getQueryParams(), 'id');

        $project = Project::query()
            ->whereVisibleTo($actor)
            ->where(function (Builder $q) use ($key) {
                $q->where('slug', $key);
                if (ctype_digit($key)) {
                    $q->orWhere('id', (int) $key);
                }
            })
            ->with(['user', 'primaryCategory', 'categories', 'fieldValues.field', 'links.button', 'likes', 'coAuthors.user'])
            ->first();

        if (! $project) {
            throw new PermissionDeniedException();
        }

        return new JsonResponse(['data' => $this->serializer->serialize($project, $actor, true, $request)]);
    }
}
