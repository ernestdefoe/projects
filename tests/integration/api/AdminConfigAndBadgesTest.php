<?php

namespace ErnestDefoe\Projects\Tests\integration\api;

use ErnestDefoe\Projects\Tests\integration\ProjectsTestCase;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\Test;

/** The admin's building blocks, image uploads and the FoF Badges award. */
class AdminConfigAndBadgesTest extends ProjectsTestCase
{
    #[Test]
    public function only_admins_change_categories_fields_and_buttons()
    {
        foreach ([['POST', '/api/projects/config/categories', ['name' => 'Art']], ['POST', '/api/projects/config/fields', ['name' => 'Pages']], ['POST', '/api/projects/config/buttons', ['label' => 'Shop']], ['DELETE', '/api/projects/config/categories/1', null], ['POST', '/api/projects/config/reorder', ['kind' => 'categories', 'ids' => [2, 1]]]] as [$method, $path, $attributes]) {
            [$status] = $this->api($method, $path, 4, $attributes);
            $this->assertSame(403, $status, "A moderator: $method $path");
        }

        [$status, $body] = $this->api('POST', '/api/projects/config/categories', 1, ['name' => 'Fine Art']);
        $this->assertSame(201, $status);
        $this->assertSame('fine-art', $body['data']['slug']);
    }

    #[Test]
    public function the_config_everyone_reads_follows_admin_changes()
    {
        [, $before] = $this->api('GET', '/api/projects/config');
        $this->assertSame(['Books', 'Games'], array_column($before['data']['categories'], 'name'));
        $this->assertArrayNotHasKey('badges', $before['data'], 'The badge list is for admins');

        $this->api('POST', '/api/projects/config/reorder', 1, ['kind' => 'categories', 'ids' => [2, 1]]);

        [, $after] = $this->api('GET', '/api/projects/config');
        $this->assertSame(['Games', 'Books'], array_column($after['data']['categories'], 'name'), 'The cached copy was refreshed');

        $this->api('POST', '/api/projects/config/buttons', 1, ['label' => 'Steam', 'allowedDomains' => ['https://store.steampowered.com/app/1', '.Steamcommunity.com']]);

        [, $after] = $this->api('GET', '/api/projects/config');
        $this->assertSame(['store.steampowered.com', 'steamcommunity.com'], $after['data']['buttons'][0]['allowedDomains'], 'Domains are stored as bare hosts');
    }

    private function upload(string $contents, string $clientType): int
    {
        $file = new UploadedFile((new StreamFactory())->createStream($contents), strlen($contents), UPLOAD_ERR_OK, 'cover.png', $clientType);

        return $this->send($this->request('POST', '/api/projects/upload-image', ['authenticatedAs' => 2])->withUploadedFiles(['image' => $file]))->getStatusCode();
    }

    #[Test]
    public function an_upload_is_judged_by_its_bytes_not_its_claimed_type()
    {
        $this->assertSame(422, $this->upload('<?php echo "hi";', 'image/png'), 'A script claiming to be a PNG');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $this->assertSame(200, $this->upload($png, 'application/octet-stream'), 'A real PNG, whatever it claims');
    }

    #[Test]
    public function publishing_awards_the_configured_badge_once()
    {
        $this->extension('fof-badges');
        $this->setting('ernestdefoe-projects.publish_badge_id', '7');
        $this->app();
        $this->database()->table('fof_badge_cat')->insert(['id' => 1, 'name' => 'Makers', 'slug' => 'makers', 'is_enabled' => true, 'order' => 0]);
        $this->database()->table('fof_badges')->insert(['id' => 7, 'category_id' => 1, 'name' => 'Creator', 'slug' => 'creator', 'icon' => 'fas fa-star', 'is_active' => true, 'is_visible' => true, 'earned_count' => 0, 'order' => 0]);

        // Project 2 is pending; approving it publishes it for its author, user 3.
        [$status] = $this->api('POST', '/api/projects/2/moderate', 4, ['action' => 'approve']);
        $this->assertSame(200, $status);

        $this->api('POST', '/api/projects', 3, ['title' => 'Second']);
        $this->api('POST', '/api/projects/2/moderate', 4, ['action' => 'reject']);
        $this->api('POST', '/api/projects/2/moderate', 4, ['action' => 'approve']);

        $this->assertSame(1, $this->database()->table('fof_badge_user')->where('user_id', 3)->where('badge_id', 7)->count());
    }
}
