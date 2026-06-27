<?php

namespace ErnestDefoe\Projects\Api\Controller;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/projects/upload-image — store a project image on the public assets
 * disk and return its URL. Self-contained (no fof/upload dependency).
 */
class UploadImageController implements RequestHandlerInterface
{
    private const DEFAULT_MAX_MB = 4;
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private Factory $filesystem,
        private TranslatorInterface $translator,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    /** Admin-configurable max cover-image size, in bytes (defaults to 4 MB). */
    private function maxBytes(): int
    {
        $mb = (int) $this->settings->get('ernestdefoe-projects.max_image_mb', self::DEFAULT_MAX_MB);

        return ($mb > 0 ? $mb : self::DEFAULT_MAX_MB) * 1024 * 1024;
    }

    private function t(string $key, array $params = []): string
    {
        return $this->translator->trans('ernestdefoe-projects.api.' . $key, $params);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $actor->assertCan('projects.create');

        /** @var UploadedFileInterface|null $file */
        $file = self::firstFile($request->getUploadedFiles());

        if (! $file || $file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException(['image' => $this->t('upload_none')]);
        }

        $maxBytes = $this->maxBytes();
        if ($file->getSize() > $maxBytes) {
            throw new ValidationException(['image' => $this->t('upload_too_large', ['{max}' => (int) ($maxBytes / 1024 / 1024)])]);
        }

        // SECURITY: never trust the client-declared Content-Type — it is fully
        // attacker-controlled. Sniff the real type from the file's magic bytes and
        // reject anything that isn't a genuine image of an allowed type. This also
        // forces the stored extension from the sniffed type, so a disguised payload
        // cannot pick its own extension.
        $contents = $file->getStream()->getContents();
        $info = @getimagesizefromstring($contents);
        $mime = strtolower((string) ($info['mime'] ?? ''));

        if (! isset(self::MIME_EXT[$mime])) {
            throw new ValidationException(['image' => $this->t('upload_type')]);
        }

        $disk = $this->filesystem->disk('flarum-assets');
        $name = 'projects/' . date('Y/m') . '/' . Str::random(24) . '.' . self::MIME_EXT[$mime];

        $disk->put($name, $contents, 'public');

        return new JsonResponse(['data' => ['url' => $disk->url($name)]]);
    }

    /** Return the first uploaded file from a (possibly nested) files array. */
    private static function firstFile(array $files): ?UploadedFileInterface
    {
        foreach ($files as $f) {
            if ($f instanceof UploadedFileInterface) {
                return $f;
            }
            if (is_array($f)) {
                $nested = self::firstFile($f);
                if ($nested) {
                    return $nested;
                }
            }
        }

        return null;
    }
}
