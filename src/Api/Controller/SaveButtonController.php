<?php

namespace ErnestDefoe\Projects\Api\Controller;

use ErnestDefoe\Projects\Api\DefinitionSerializer;
use ErnestDefoe\Projects\Model\ProjectButton;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/projects/config/buttons  +  PATCH …/{id} — create/update a button slot (admin). */
class SaveButtonController implements RequestHandlerInterface
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $attrs = (array) Arr::get((array) $request->getParsedBody(), 'data.attributes', []);
        $id = (int) Arr::get($request->getQueryParams(), 'id', 0);

        $button = $id ? ProjectButton::query()->findOrFail($id) : new ProjectButton();

        $label = trim((string) Arr::get($attrs, 'label', ''));
        if ($label === '') {
            throw new ValidationException(['label' => $this->translator->trans('ernestdefoe-projects.api.button_label_required')]);
        }
        $button->label = $label;

        if (! $button->exists) {
            $button->key = self::uniqueKey(Str::slug($label, '_') ?: 'button');
        }

        // Normalise allowed domains to bare host suffixes (strip scheme/path).
        $domains = collect((array) Arr::get($attrs, 'allowedDomains', []))
            ->map(function ($d) {
                $d = trim((string) $d);
                if ($d === '') {
                    return null;
                }
                $host = parse_url(str_contains($d, '://') ? $d : 'https://' . $d, PHP_URL_HOST);
                return strtolower(ltrim((string) ($host ?: $d), '.'));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $button->allowed_domains = $domains ?: null;
        $button->icon = trim((string) Arr::get($attrs, 'icon', '')) ?: null;
        $button->allow_custom_label = (bool) Arr::get($attrs, 'allowCustomLabel', true);
        $button->is_required = (bool) Arr::get($attrs, 'isRequired', false);
        $button->is_primary = (bool) Arr::get($attrs, 'isPrimary', false);

        // Per-tag restriction: empty list => available for every category.
        $cats = array_values(array_filter(array_map('intval', (array) Arr::get($attrs, 'categoryIds', [])), fn ($i) => $i > 0));
        $button->category_ids = $cats ?: null;

        $button->position = (int) Arr::get($attrs, 'position', $button->position ?? 0);
        $button->save();

        return new JsonResponse(['data' => DefinitionSerializer::button($button)], $id ? 200 : 201);
    }

    private static function uniqueKey(string $base): string
    {
        $key = $base;
        $i = 2;
        while (ProjectButton::query()->where('key', $key)->exists()) {
            $key = $base . '_' . $i++;
        }

        return $key;
    }
}
