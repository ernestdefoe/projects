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

/**
 * POST /api/projects/{id}/feature — the owner toggles which of their projects is
 * featured (drives the profile header + username badge). At most one per user.
 */
class FeatureProjectController implements RequestHandlerInterface
{
    public function __construct(private ProjectSerializer $serializer)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $id = (int) Arr::get($request->getQueryParams(), 'id');
        $project = Project::query()->findOrFail($id);

        if (! ProjectSerializer::canFeature($project, $actor)) {
            $actor->assertPermission(false);
        }

        $makeFeatured = ! $project->is_featured;

        // One featured project per user — clear any others first.
        Project::query()
            ->where('user_id', $actor->id)
            ->where('id', '!=', $project->id)
            ->where('is_featured', true)
            ->update(['is_featured' => false]);

        $project->is_featured = $makeFeatured;
        $project->save(); // model hook refreshes the user's featured snapshot

        $project->load(['user', 'primaryCategory', 'categories', 'fieldValues.field', 'links.button', 'likes', 'coAuthors.user']);

        // Pass $full=true so the response includes contentHtml — otherwise the
        // frontend (which replaces its project with this data) loses the rendered
        // description after toggling featured.
        return new JsonResponse(['data' => $this->serializer->serialize($project, $actor, true, $request)]);
    }
}
