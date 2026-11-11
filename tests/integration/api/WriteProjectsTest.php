<?php

namespace ErnestDefoe\Projects\Tests\integration\api;

use ErnestDefoe\Projects\Tests\integration\ProjectsTestCase;
use PHPUnit\Framework\Attributes\Test;

/** Creating, editing, moderating, liking, featuring and deleting projects. */
class WriteProjectsTest extends ProjectsTestCase
{
    private function projectCount(): int
    {
        return $this->database()->table('projects')->count();
    }

    #[Test]
    public function a_members_project_waits_for_review_and_an_admins_is_published()
    {
        [$status, $body] = $this->api('POST', '/api/projects', 2, ['title' => 'Fresh Game', 'categoryIds' => [2]]);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame('pending', $body['data']['status']);
        $this->assertSame('fresh-game', $body['data']['slug']);
        $this->assertSame('Games', $body['data']['primaryCategory']['name']);

        [$status, $body] = $this->api('POST', '/api/projects', 1, ['title' => 'Fresh Game']);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame('published', $body['data']['status']);
        $this->assertNotSame('fresh-game', $body['data']['slug'], 'A taken slug gets a suffix');
    }

    #[Test]
    public function creating_needs_the_permission()
    {
        $this->app();
        $this->database()->table('group_permission')->where('permission', 'projects.create')->delete();

        [$status] = $this->api('POST', '/api/projects', 2, ['title' => 'Nope']);

        $this->assertSame(403, $status);
    }

    #[Test]
    public function an_invalid_project_is_refused_and_nothing_is_left_behind()
    {
        $this->app();
        $before = $this->projectCount();

        $invalid = [
            ['title' => ''],
            ['title' => 'X', 'image' => 'javascript:alert(1)'],
            ['title' => 'X', 'links' => [['url' => 'data:text/html,hi']]],
            ['title' => 'X', 'links' => [['url' => '//evil.example/x']]],
        ];

        foreach ($invalid as $attributes) {
            [$status] = $this->api('POST', '/api/projects', 2, $attributes);
            $this->assertSame(422, $status, json_encode($attributes));
        }

        $this->assertSame($before, $this->projectCount(), 'A refused create rolls back, leaving no orphan row');
    }

    #[Test]
    public function the_short_description_follows_the_admin_character_limit()
    {
        [$status] = $this->api('POST', '/api/projects', 2, ['title' => 'Default', 'excerpt' => str_repeat('a', 281)]);
        $this->assertSame(422, $status, 'Over the default 280 is refused');

        [$status] = $this->api('POST', '/api/projects', 2, ['title' => 'Default', 'excerpt' => str_repeat('a', 280)]);
        $this->assertSame(201, $status, 'Exactly the default limit saves');
    }

    #[Test]
    public function a_raised_short_description_limit_lets_a_longer_one_save()
    {
        $this->setting('ernestdefoe-projects.excerpt_limit', 400);

        [$status, $body] = $this->api('POST', '/api/projects', 2, ['title' => 'Longer', 'excerpt' => str_repeat('b', 350)]);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame(350, mb_strlen($body['data']['excerpt']));

        [$status] = $this->api('POST', '/api/projects', 2, ['title' => 'Too long', 'excerpt' => str_repeat('b', 401)]);
        $this->assertSame(422, $status);

        [, $forum] = $this->api('GET', '/api', 2);
        $this->assertSame(400, $forum['data']['attributes']['projectsExcerptLimit'], 'The composer counter uses the same limit');
    }

    #[Test]
    public function the_short_description_limit_never_exceeds_the_column_size()
    {
        $this->setting('ernestdefoe-projects.excerpt_limit', 5000);

        [$status] = $this->api('POST', '/api/projects', 2, ['title' => 'Huge', 'excerpt' => str_repeat('c', 601)]);
        $this->assertSame(422, $status, 'Capped at the 600-character column');

        [$status] = $this->api('POST', '/api/projects', 2, ['title' => 'Max', 'excerpt' => str_repeat('c', 600)]);
        $this->assertSame(201, $status);

        [, $forum] = $this->api('GET', '/api', 2);
        $this->assertSame(600, $forum['data']['attributes']['projectsExcerptLimit']);
    }

    #[Test]
    public function a_button_only_accepts_links_on_its_allowed_domains()
    {
        $this->app();
        $this->database()->table('project_buttons')->insert(['id' => 1, 'label' => 'Trailer', 'key' => 'trailer', 'allowed_domains' => json_encode(['youtube.com']), 'allow_custom_label' => false]);

        [$status] = $this->api('POST', '/api/projects', 2, ['title' => 'Bad', 'links' => [['buttonId' => 1, 'url' => 'https://youtube.com.evil.example/x']]]);
        $this->assertSame(422, $status);

        [$status, $body] = $this->api('POST', '/api/projects', 2, ['title' => 'Good', 'links' => [['buttonId' => 1, 'url' => 'https://www.youtube.com/watch?v=1', 'label' => 'Ignored']]]);
        $this->assertSame(201, $status);
        $this->assertSame('Trailer', $body['data']['links'][0]['label'], 'This button does not take a custom label');
    }

    #[Test]
    public function a_required_custom_field_must_be_filled_and_a_select_only_takes_its_choices()
    {
        $this->app();
        $this->database()->table('project_fields')->insert([
            ['id' => 1, 'name' => 'Pages', 'key' => 'pages', 'type' => 'number', 'options' => null, 'is_required' => true],
            ['id' => 2, 'name' => 'Genre', 'key' => 'genre', 'type' => 'select', 'options' => json_encode(['Horror', 'Comedy']), 'is_required' => false],
        ]);

        [$status] = $this->api('POST', '/api/projects', 2, ['title' => 'A', 'fieldValues' => []]);
        $this->assertSame(422, $status, 'Required field missing');

        [$status] = $this->api('POST', '/api/projects', 2, ['title' => 'B', 'fieldValues' => ['1' => 'lots']]);
        $this->assertSame(422, $status, 'Not a number');

        [$status] = $this->api('POST', '/api/projects', 2, ['title' => 'C', 'fieldValues' => ['1' => '300', '2' => 'Romance']]);
        $this->assertSame(422, $status, 'Not one of the choices');

        [$status, $body] = $this->api('POST', '/api/projects', 2, ['title' => 'D', 'fieldValues' => ['pages' => '300', 'genre' => 'Horror']]);
        $this->assertSame(201, $status);
        $this->assertSame(['300', 'Horror'], array_column($body['data']['fields'], 'value'));
    }

    #[Test]
    public function only_the_author_or_a_moderator_may_edit_or_delete()
    {
        [$status] = $this->api('PATCH', '/api/projects/1', 3, ['title' => 'Hijacked']);
        $this->assertSame(403, $status);

        [$status] = $this->api('DELETE', '/api/projects/1', 3);
        $this->assertSame(403, $status);

        [$status, $body] = $this->api('PATCH', '/api/projects/1', 2, ['title' => 'My Novel, Revised']);
        $this->assertSame(200, $status);
        $this->assertSame('My Novel, Revised', $body['data']['title']);
        $this->assertSame('published', $body['data']['status'], 'Editing a published project keeps it published');

        [$status] = $this->api('PATCH', '/api/projects/2', 4, ['excerpt' => 'Tidied by a moderator']);
        $this->assertSame(200, $status);

        [$status] = $this->api('DELETE', '/api/projects/1', 2);
        $this->assertSame(204, $status);
        $this->assertNull($this->database()->table('projects')->find(1));
    }

    #[Test]
    public function editing_a_rejected_project_resubmits_it_unless_a_moderator_does()
    {
        [, $body] = $this->api('PATCH', '/api/projects/3', 4, ['excerpt' => 'Moderator note']);
        $this->assertSame('rejected', $body['data']['status']);

        [, $body] = $this->api('PATCH', '/api/projects/3', 2, ['excerpt' => 'Longer now']);
        $this->assertSame('pending', $body['data']['status']);
        $this->assertNull($body['data']['rejectionReason']);
    }

    #[Test]
    public function only_moderators_approve_or_reject()
    {
        [$status] = $this->api('POST', '/api/projects/2/moderate', 2, ['action' => 'approve']);
        $this->assertSame(403, $status);

        [$status, $body] = $this->api('POST', '/api/projects/2/moderate', 4, ['action' => 'reject', 'reason' => 'Needs a cover']);
        $this->assertSame(200, $status);
        $this->assertSame('rejected', $body['data']['status']);
        $this->assertSame('Needs a cover', $body['data']['rejectionReason']);

        [, $body] = $this->api('POST', '/api/projects/2/moderate', 4, ['action' => 'approve']);
        $this->assertSame('published', $body['data']['status']);
        $this->assertNull($body['data']['rejectionReason']);
    }

    #[Test]
    public function a_like_toggles_and_keeps_the_count()
    {
        [, $body] = $this->api('POST', '/api/projects/1/like', 3);
        $this->assertSame([1, true], [$body['data']['likesCount'], $body['data']['liked']]);

        [, $body] = $this->api('POST', '/api/projects/1/like', 4);
        $this->assertSame(2, $body['data']['likesCount']);

        [, $body] = $this->api('POST', '/api/projects/1/like', 3);
        $this->assertSame([1, false], [$body['data']['likesCount'], $body['data']['liked']]);

        [$status] = $this->api('POST', '/api/projects/2/like', 2);
        $this->assertSame(404, $status, 'A project the member cannot see cannot be liked');
    }

    #[Test]
    public function the_owner_features_a_published_project_and_it_reaches_their_profile()
    {
        // A newer published project, which would be shown if nothing were featured.
        $this->app();
        $this->database()->table('projects')->insert(['id' => 5, 'user_id' => 2, 'title' => 'Newer', 'slug' => 'newer', 'status' => 'published', 'likes_count' => 0, 'created_at' => '2026-06-01', 'updated_at' => '2026-06-01']);

        [$status] = $this->api('POST', '/api/projects/1/feature', 3);
        $this->assertSame(403, $status, 'Not the owner');

        [$status] = $this->api('POST', '/api/projects/3/feature', 2);
        $this->assertSame(403, $status, 'Not published');

        [$status, $body] = $this->api('POST', '/api/projects/1/feature', 2);
        $this->assertSame(200, $status);
        $this->assertTrue($body['data']['isFeatured']);

        [, $user] = $this->api('GET', '/api/users/2');
        $this->assertSame(['id' => 1, 'title' => 'My Novel', 'slug' => 'my-novel', 'icon' => null, 'color' => null, 'categoryName' => 'Books'], $user['data']['attributes']['projectFeatured']);
    }

    #[Test]
    public function with_the_badge_turned_off_no_snapshot_is_sent_and_nothing_can_be_featured()
    {
        $this->setting('ernestdefoe-projects.show_featured_badge', '0');
        $this->app();
        $this->database()->table('users')->where('id', 2)->update(['projects_featured' => json_encode(['id' => 1, 'title' => 'My Novel', 'slug' => 'my-novel'])]);

        [, $user] = $this->api('GET', '/api/users/2');
        $this->assertArrayNotHasKey('projectFeatured', $user['data']['attributes']);

        [, $project] = $this->api('GET', '/api/projects/1', 2);
        $this->assertFalse($project['data']['canFeature'], 'The Feature on profile button is hidden');

        [$status] = $this->api('POST', '/api/projects/1/feature', 2);
        $this->assertSame(403, $status);
    }

    #[Test]
    public function co_authors_link_forum_users_in_one_lookup_and_keep_other_names_as_text()
    {
        $this->app();
        $db = $this->database();
        $db->enableQueryLog();

        [$status, $body] = $this->api('POST', '/api/projects', 1, ['title' => 'Team Work', 'coAuthors' => ['maker', 'mod', 'Ghost Writer', ' ', 'normal']]);
        $this->assertSame(201, $status, json_encode($body));

        $lookups = array_filter($db->getQueryLog(), fn ($q) => str_contains($q['query'], 'username'));
        $db->disableQueryLog();
        $this->assertCount(1, $lookups, 'Every co-author username is resolved in a single query');

        $authors = $body['data']['coAuthors'];
        $this->assertSame(['maker', 'mod'], [$authors[0]['username'], $authors[1]['username']]);
        $this->assertSame(['name' => 'Ghost Writer'], $authors[2]);
        $this->assertSame('normal', $authors[3]['username']);
        $this->assertCount(4, $authors, 'A blank entry is skipped');
    }

    #[Test]
    public function a_published_create_refreshes_the_authors_profile_snapshot_once_with_its_tag()
    {
        $this->app();
        $db = $this->database();
        $db->table('users')->where('id', 1)->update(['projects_featured' => null]);

        // The harness runs after-commit callbacks inline. Defer them as a real
        // request does: until the create's own transaction commits (level 1 is
        // the test's wrapping transaction).
        $db->setTransactionManager(new class extends \Illuminate\Database\DatabaseTransactionsManager {
            public function afterCommitCallbacksShouldBeExecuted($level)
            {
                return $level === 1;
            }
        });
        $db->enableQueryLog();

        [$status] = $this->api('POST', '/api/projects', 1, ['title' => 'Brand New', 'categoryIds' => [2]]);
        $this->assertSame(201, $status);

        $writes = array_filter($db->getQueryLog(), fn ($q) => str_contains($q['query'], 'projects_featured') && str_starts_with(strtolower($q['query']), 'update'));
        $db->disableQueryLog();
        $this->assertCount(1, $writes, 'The two saves of one create write the snapshot once');

        $snapshot = json_decode($db->table('users')->where('id', 1)->value('projects_featured'), true);
        $this->assertSame(['Brand New', 'Games'], [$snapshot['title'], $snapshot['categoryName']], 'The snapshot sees the final primary tag');
    }
}
