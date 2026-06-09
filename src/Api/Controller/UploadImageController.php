<?php

namespace ErnestDefoe\Projects\Api\Controller;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
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
    private const MAX_BYTES = 4 * 1024 * 1024; // 4 MB
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private Factory $filesystem,
        private TranslatorInterface $translator,
    ) {
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

        if ($file->getSize() > self::MAX_BYTES) {
            throw new ValidationException(['image' => $this->t('upload_too_large', ['{max}' => (int) (self::MAX_BYTES / 1024 / 1024)])]);
        }

        $mime = strtolower((string) $file->getClientMediaType());
        if (! isset(self::MIME_EXT[$mime])) {
            // Fall back to sniffing the stream when the client mime is missing/wrong.
            $tmp = $file->getStream()->getMetadata('uri');
            $info = $tmp ? @getimagesize($tmp) : false;
            $mime = $info['mime'] ?? '';
        }
        if (! isset(self::MIME_EXT[$mime])) {
            throw new ValidationException(['image' => $this->t('upload_type')]);
        }

        $disk = $this->filesystem->disk('flarum-assets');
        $name = 'projects/' . date('Y/m') . '/' . Str::random(24) . '.' . self::MIME_EXT[$mime];

        $disk->put($name, $file->getStream()->getContents(), 'public');

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
