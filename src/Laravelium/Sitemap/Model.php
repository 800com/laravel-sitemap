<?php

namespace Laravelium\Sitemap;

use Carbon\Carbon;
use DateTime;
use DateTimeInterface;

/**
 * Model class for laravel-sitemap package.
 *
 * @author Rumen Damyanov <r@alfamatter.com>
 *
 * @version 7.0.1
 *
 * @link https://gitlab.com/Laravelium
 *
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Model
{
    public bool $testing = false;

    /**
     * @var mixed[]
     */
    private array $items = [];

    /**
     * @var mixed[]
     */
    private array $sitemaps = [];

    private ?string $title = null;

    private ?string $link = null;

    /**
     * Enable or disable xsl styles.
     */
    private bool $useStyles = true;

    /**
     * Set custom location for xsl styles (must end with slash).
     */
    private ?string $sloc = '/vendor/sitemap/styles/';

    /**
     * Enable or disable cache.
     */
    private bool $useCache = false;

    /**
     * Unique cache key.
     */
    private string $cacheKey = 'laravel-sitemap.';

    /**
     * Cache duration, can be int or timestamp.
     */
    private Carbon|DateTimeInterface|int $cacheDuration = 3600;

    /**
     * Escaping html entities.
     */
    private bool $escaping = true;

    /**
     * Use limitSize() for big sitemaps.
     */
    private bool $useLimitSize = false;

    /**
     * Custom max size for limitSize().
     */
    private ?int $maxSize = null;

    /**
     * Use gzip compression.
     */
    private bool $useGzip = false;

    /**
     * Populating model variables from configuation file.
     *
     * @param array{
     *     use_cache?: bool,
     *     cache_key?: string,
     *     cache_duration?: int,
     *     escaping?: bool,
     *     use_limit_size?: bool,
     *     use_styles?: bool,
     *     styles_location?: string,
     *     max_size?: int,
     *     testing?: bool,
     *     use_gzip?: bool,
     * } $config
     */
    public function __construct(array $config)
    {
        $this->useCache = $config['use_cache'] ?? $this->useCache;
        $this->cacheKey = $config['cache_key'] ?? $this->cacheKey;
        $this->cacheDuration = $config['cache_duration'] ?? $this->cacheDuration;
        $this->escaping = $config['escaping'] ?? $this->escaping;
        $this->useLimitSize = $config['use_limit_size'] ?? $this->useLimitSize;
        $this->useStyles = $config['use_styles'] ?? $this->useStyles;
        $this->sloc = $config['styles_location'] ?? $this->sloc;
        $this->maxSize = $config['max_size'] ?? $this->maxSize;
        $this->testing = $config['testing'] ?? $this->testing;
        $this->useGzip = $config['use_gzip'] ?? $this->useGzip;
    }

    /**
     * Returns $items array.
     *
     * @return mixed[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Returns $sitemaps array.
     *
     * @return mixed[]
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * Returns $title value.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Returns $link value.
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * Returns $useStyles value.
     */
    public function getUseStyles(): bool
    {
        return $this->useStyles;
    }

    /**
     * Returns $sloc value.
     */
    public function getSloc(): ?string
    {
        return $this->sloc;
    }

    /**
     * Returns $useCache value.
     */
    public function getUseCache(): bool
    {
        return $this->useCache;
    }

    /**
     * Returns $CacheKey value.
     */
    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }

    /**
     * Returns $CacheDuration value.
     */
    public function getCacheDuration(): Carbon|DateTimeInterface|int
    {
        return $this->cacheDuration;
    }

    /**
     * Returns $escaping value.
     */
    public function getEscaping(): bool
    {
        return $this->escaping;
    }

    /**
     * Returns $useLimitSize value.
     */
    public function getUseLimitSize(): bool
    {
        return $this->useLimitSize;
    }

    /**
     * Returns $maxSize value.
     */
    public function getMaxSize(): ?int
    {
        return $this->maxSize;
    }

    /**
     * Returns $useGzip value.
     */
    public function getUseGzip(): bool
    {
        return $this->useGzip;
    }

    /**
     * Sets $escaping value.
     */
    public function setEscaping(bool $b): void
    {
        $this->escaping = $b;
    }

    /**
     * Adds item to $items array.
     *
     * @param mixed[] $items
     */
    public function setItems(array $items): void
    {
        $this->items[] = $items;
    }

    /**
     * Adds sitemap to $sitemaps array.
     *
     * @param mixed[] $sitemap
     */
    public function setSitemaps(array $sitemap): void
    {
        $this->sitemaps[] = $sitemap;
    }

    /**
     * Sets $title value.
     */
    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    /**
     * Sets $link value.
     */
    public function setLink(?string $link): void
    {
        $this->link = $link;
    }

    /**
     * Sets $useStyles value.
     */
    public function setUseStyles(bool $useStyles): void
    {
        $this->useStyles = $useStyles;
    }

    /**
     * Sets $sloc value.
     */
    public function setSloc(?string $sloc): void
    {
        $this->sloc = $sloc;
    }

    /**
     * Sets $useLimitSize value.
     */
    public function setUseLimitSize(bool $useLimitSize): void
    {
        $this->useLimitSize = $useLimitSize;
    }

    /**
     * Sets $maxSize value.
     */
    public function setMaxSize(?int $maxSize): void
    {
        $this->maxSize = $maxSize;
    }

    /**
     * Sets $useGzip value.
     *
     * @param bool $useGzip
     */
    public function setUseGzip(bool $useGzip=true): void
    {
        $this->useGzip = $useGzip;
    }

    /**
     * Limit size of $items array to 50000 elements (1000 for google-news).
     */
    public function limitSize(int $max = 50000): void
    {
        $this->items = array_slice($this->items, 0, $max);
    }

    /**
     * Reset $items array.
     *
     * @param mixed[] $items
     */
    public function resetItems(array $items = []): void
    {
        $this->items = $items;
    }

    /**
     * Reset $sitemaps array.
     *
     * @param mixed[] $sitemaps
     */
    public function resetSitemaps(array $sitemaps = []): void
    {
        $this->sitemaps = $sitemaps;
    }

    /**
     * Set use cache value.
     */
    public function setUseCache(bool $useCache = true): void
    {
        $this->useCache = $useCache;
    }

    /**
     * Set cache key value.
     *
     * @param string $cacheKey
     */
    public function setCacheKey(string $cacheKey): void
    {
        $this->cacheKey = $cacheKey;
    }

    /**
     * Set cache duration value.
     */
    public function setCacheDuration(Carbon|DateTimeInterface|int $cacheDuration): void
    {
        $this->cacheDuration = $cacheDuration;
    }
}
