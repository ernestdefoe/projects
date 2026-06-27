<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\ProjectSerializer;
use ErnestDefoe\Projects\Model\Project;
use ErnestDefoe\Projects\Model\ProjectCategory;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/projects — browse/search projects.
 *
 * Query params: q (search), category (slug), user (id), status (moderator
 * only), sort (recent|popular|title), page, perPage.
 */
class ListProjectsController implements RequestHandlerInterface
{
    private const PER_PAGE = 20;

    public function __construct(private ProjectSerializer $serializer)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $params = $request->getQueryParams();

        $query = Project::query()->whereVisibleTo($actor);

        // Free-text search across title + excerpt.
        if ($q = trim((string) Arr::get($params, 'q', ''))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(function (Builder $sub) use ($like) {
                $sub->where('title', 'like', $like)->orWhere('excerpt', 'like', $like);
            });
        }

        // Category filter (by slug) — matches primary or any assigned category.
        if ($categorySlug = trim((string) Arr::get($params, 'category', ''))) {
            $category = ProjectCategory::query()->where('slug', $categorySlug)->first();
            if ($category) {
                $query->whereHas('categories', fn (Builder $c) => $c->where('project_categories.id', $category->id));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Author filter (used by the profile tab).
        if ($userId = (int) Arr::get($params, 'user', 0)) {
            $query->where('user_id', $userId);
        }

        // Status filter — only honoured for moderators.
        $status = trim((string) Arr::get($params, 'status', ''));
        if ($status !== '' && ($actor->isAdmin() || $actor->hasPermission('projects.moderate'))) {
            $query->where('status', $status);
        }

        switch (Arr::get($params, 'sort', 'recent')) {
            case 'popular':
                $query->orderByDesc('likes_count')->orderByDesc('id');
                break;
            case 'title':
                $query->orderBy('title');
                break;
            default:
                $query->orderByDesc('created_at')->orderByDesc('id');
        }

        $perPage = min(50, max(1, (int) Arr::get($params, 'perPage', self::PER_PAGE)));
        $page = max(1, (int) Arr::get($params, 'page', 1));
        $total = (clone $query)->count();

        $projects = $query
            ->with(['user', 'primaryCategory', 'categories', 'fieldValues.field', 'links.button', 'likes', 'coAuthors.user'])
            ->forPage($page, $perPage)
            ->get();

        $data = $projects->map(fn (Project $p) => $this->serializer->serialize($p, $actor))->all();

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'total'    => $total,
                'page'     => $page,
                'perPage'  => $perPage,
                'hasMore'  => ($page * $perPage) < $total,
            ],
        ]);
    }
}
