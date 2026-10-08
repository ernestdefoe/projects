<?php

namespace ErnestDefoe\Projects\Tests\integration;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

/**
 * Users: 1 admin, 2 member, 3 member, 4 moderator.
 * Projects: 1 published (by 2, category "books"), 2 pending (by 3),
 * 3 rejected (by 2), 4 published (by 3, user 2 a co-author).
 */
abstract class ProjectsTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-projects');

        $at = fn (int $days) => Carbon::parse('2026-01-01')->addDays($days);

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'maker', 'email' => 'maker@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'mod', 'email' => 'mod@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 3],
                ['user_id' => 4, 'group_id' => 4],
            ],
            'project_categories' => [
                ['id' => 1, 'name' => 'Books', 'slug' => 'books', 'position' => 0],
                ['id' => 2, 'name' => 'Games', 'slug' => 'games', 'position' => 1],
            ],
            'projects' => [
                ['id' => 1, 'user_id' => 2, 'primary_category_id' => 1, 'title' => 'My Novel', 'slug' => 'my-novel', 'excerpt' => 'A long book', 'content' => 'It was a **dark** night', 'status' => 'published', 'likes_count' => 0, 'created_at' => $at(1), 'updated_at' => $at(1)],
                ['id' => 2, 'user_id' => 3, 'title' => 'Secret Draft', 'slug' => 'secret-draft', 'status' => 'pending', 'likes_count' => 0, 'created_at' => $at(2), 'updated_at' => $at(2)],
                ['id' => 3, 'user_id' => 2, 'title' => 'Turned Down', 'slug' => 'turned-down', 'status' => 'rejected', 'rejection_reason' => 'Too short', 'likes_count' => 0, 'created_at' => $at(3), 'updated_at' => $at(3)],
                ['id' => 4, 'user_id' => 3, 'title' => '50% Done', 'slug' => '50-done', 'status' => 'published', 'likes_count' => 5, 'created_at' => $at(4), 'updated_at' => $at(4)],
            ],
            'project_category' => [
                ['project_id' => 1, 'category_id' => 1],
            ],
            'project_authors' => [
                ['project_id' => 4, 'user_id' => 2, 'name' => null, 'position' => 0],
            ],
        ]);
    }

    /** @return array{0: int, 1: array<string, mixed>|null} */
    protected function api(string $method, string $path, ?int $actor = null, ?array $attributes = null, array $query = []): array
    {
        $options = $actor ? ['authenticatedAs' => $actor] : [];
        if ($attributes !== null) {
            $options['json'] = ['data' => ['attributes' => $attributes]];
        }

        $response = $this->send($this->request($method, $path, $options)->withQueryParams($query));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    /** @return int[] */
    protected function listIds(?int $actor = null, array $query = []): array
    {
        [$status, $body] = $this->api('GET', '/api/projects', $actor, null, $query);
        $this->assertSame(200, $status);

        return array_column($body['data'], 'id');
    }
}
