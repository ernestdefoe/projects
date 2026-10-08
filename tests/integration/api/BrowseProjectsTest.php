<?php

namespace ErnestDefoe\Projects\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Projects\Tests\integration\ProjectsTestCase;
use PHPUnit\Framework\Attributes\Test;

/** Listing, searching and showing projects, and who sees what. */
class BrowseProjectsTest extends ProjectsTestCase
{
    #[Test]
    public function guests_see_only_published_projects()
    {
        $this->assertSame([4, 1], $this->listIds());
    }

    #[Test]
    public function authors_also_see_their_own_pending_and_rejected_projects()
    {
        $this->assertSame([4, 3, 1], $this->listIds(2));
        $this->assertSame([4, 2, 1], $this->listIds(3));
    }

    #[Test]
    public function moderators_see_everything_and_alone_may_filter_by_status()
    {
        $this->assertSame([4, 3, 2, 1], $this->listIds(4));
        $this->assertSame([2], $this->listIds(4, ['status' => 'pending']));
        $this->assertSame([4, 3, 1], $this->listIds(2, ['status' => 'pending']), 'Ignored for a member');
    }

    #[Test]
    public function search_matches_title_or_excerpt_and_treats_wildcards_literally()
    {
        $this->assertSame([1], $this->listIds(null, ['q' => 'long book']));
        $this->assertSame([4], $this->listIds(null, ['q' => '50%']));
        $this->assertSame([4], $this->listIds(null, ['q' => '%']), 'A bare % matches a literal %, not everything');
        $this->assertSame([], $this->listIds(null, ['q' => '_']), 'Nor does _ match any one character');
    }

    #[Test]
    public function the_category_filter_uses_the_slug()
    {
        $this->assertSame([1], $this->listIds(null, ['category' => 'books']));
        $this->assertSame([], $this->listIds(null, ['category' => 'nope']));
    }

    #[Test]
    public function a_members_projects_include_those_they_co_authored()
    {
        $this->assertSame([4, 1], $this->listIds(null, ['user' => 2]));
    }

    #[Test]
    public function popular_sorts_by_likes_and_pages_report_whether_there_is_more()
    {
        [, $body] = $this->api('GET', '/api/projects', null, null, ['sort' => 'popular', 'perPage' => 1]);

        $this->assertSame([4], array_column($body['data'], 'id'));
        $this->assertSame(['total' => 2, 'page' => 1, 'perPage' => 1, 'hasMore' => true], $body['meta']);
    }

    #[Test]
    public function a_full_page_of_projects_costs_no_query_per_project()
    {
        $this->app();
        $rows = [];
        for ($id = 100; $id < 130; $id++) {
            $rows[] = ['id' => $id, 'user_id' => 2 + $id % 2, 'primary_category_id' => 1 + $id % 2, 'title' => "P$id", 'slug' => "p$id", 'status' => 'published', 'likes_count' => 0, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()];
        }
        $this->database()->table('projects')->insert($rows);

        // flarum/testing fails the request if a query shape repeats per row.
        [, $body] = $this->api('GET', '/api/projects', 2, null, ['perPage' => 20]);
        $this->assertCount(20, $body['data']);

        foreach ($body['data'] as $project) {
            $this->assertNotNull($project['author'], 'Each card still carries its author');
            $this->assertNotNull($project['primaryCategory'], 'and its category');
        }
    }

    #[Test]
    public function a_project_is_shown_by_id_or_slug_with_rendered_content()
    {
        foreach (['1', 'my-novel'] as $key) {
            [$status, $body] = $this->api('GET', "/api/projects/$key");

            $this->assertSame(200, $status, $key);
            $this->assertSame('My Novel', $body['data']['title']);
            $this->assertStringContainsString('It was a **dark** night', $body['data']['contentHtml'], 'Rendered by the forum formatter');
            $this->assertSame('It was a **dark** night', $body['data']['content'], 'Raw, for the edit form');
            $this->assertSame('Books', $body['data']['primaryCategory']['name']);
            $this->assertSame('normal', $body['data']['author']['username']);
            $this->assertFalse($body['data']['canEdit']);
        }
    }

    #[Test]
    public function an_unpublished_project_is_refused_to_everyone_but_its_author_and_moderators()
    {
        [$status] = $this->api('GET', '/api/projects/2');
        $this->assertSame(403, $status);

        [$status] = $this->api('GET', '/api/projects/2', 2);
        $this->assertSame(403, $status);

        [$status, $body] = $this->api('GET', '/api/projects/2', 3);
        $this->assertSame(200, $status);
        $this->assertTrue($body['data']['canEdit']);

        [$status] = $this->api('GET', '/api/projects/2', 4);
        $this->assertSame(200, $status);
    }

    #[Test]
    public function the_forum_payload_says_who_may_create_and_moderate()
    {
        $forum = fn (?int $actor) => $this->api('GET', '/api', $actor)[1]['data']['attributes'];

        $this->assertFalse($forum(null)['canCreateProject']);
        $this->assertTrue($forum(2)['canCreateProject']);
        $this->assertFalse($forum(2)['canModerateProjects']);
        $this->assertTrue($forum(4)['canModerateProjects']);
    }
}
