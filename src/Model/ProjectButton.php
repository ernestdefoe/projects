<?php

namespace ErnestDefoe\Projects\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-defined button/link slot. Each slot may constrain the URLs projects
 * put in it to an allow-list of domains (e.g. only youtube.com links).
 *
 * @property int $id
 * @property string $label
 * @property string $key
 * @property string|null $icon
 * @property array|null $allowed_domains
 * @property bool $allow_custom_label
 * @property bool $is_required
 * @property bool $is_primary
 * @property int $position
 */
class ProjectButton extends AbstractModel
{
    protected $table = 'project_buttons';

    protected $casts = [
        'allowed_domains'    => 'array',
        'category_ids'       => 'array',
        'allow_custom_label' => 'boolean',
        'is_required'        => 'boolean',
        'is_primary'         => 'boolean',
        'position'           => 'integer',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    protected $attributes = [
        'allow_custom_label' => true,
    ];

    public function links(): HasMany
    {
        return $this->hasMany(ProjectLink::class, 'button_id');
    }

    /**
     * Does $url match this slot's allow-list? An empty list allows any host.
     * Matches the host or any subdomain of each allowed suffix.
     */
    public function allowsUrl(string $url): bool
    {
        $domains = array_filter((array) ($this->allowed_domains ?? []));
        if (empty($domains)) {
            return true;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach ($domains as $domain) {
            $domain = strtolower(ltrim(trim((string) $domain), '.'));
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.' . $domain))) {
                return true;
            }
        }

        return false;
    }
}
