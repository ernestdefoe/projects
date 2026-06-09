<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\ProjectSerializer;
use ErnestDefoe\Projects\Model\Project;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** DELETE /api/projects/{id} — delete a project (author or moderator). */
class DeleteProjectController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = (int) Arr::get($request->getQueryParams(), 'id');

        $project = Project::query()->findOrFail($id);

        if (! ProjectSerializer::canDelete($project, $actor)) {
            $actor->assertPermission(false);
        }

        $project->delete(); // FKs cascade field values / links / pivots / likes

        return new EmptyResponse(204);
    }
}
