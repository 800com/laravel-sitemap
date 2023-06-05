<?php

namespace Laravelium\Sitemap;

/**
 * Sitemap class for laravel-sitemap package.
 *
 * @author Rumen Damyanov <r@alfamatter.com>
 *
 * @version 7.0.1
 *
 * @link https://gitlab.com/Laravelium
 *
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactory;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\Filesystem as Filesystem;
use Illuminate\Http\Response;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;
use Stringable;

class Sitemap
{
    const FORMAT_SITEMAP_INDEX = 'sitemapindex';
    /**
     * Model instance.
     */
    public Model $model;

    /**
     * Using constructor we populate our model from configuration file
     * and loading dependencies.
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
    public function __construct(
        array $config,
        public readonly CacheRepository $cache,
        protected readonly ConfigRepository $configRepository,
        protected readonly Filesystem $file,
        protected readonly ResponseFactory $response,
        protected readonly ViewFactory $view
    ) {
        $this->model = new Model($config);
    }

    /**
     * Set cache options.
     */
    public function setCache(
        ?string $key = null,
        Carbon|DateTimeInterface|int $duration = null,
        bool $useCache = true
    ): void {
        $this->model->setUseCache($useCache);

        if (null !== $key) {
            $this->model->setCacheKey($key);
        }

        if (null !== $duration) {
            $this->model->setCacheDuration($duration);
        }
    }

    /**
     * Add new sitemap item to $items array.
     *
     * @param mixed[] $images
     * @param mixed[] $translations
     * @param mixed[] $videos
     * @param mixed[] $googlenews
     * @param mixed[] $alternates
     */
    public function add(
        ?string $loc,
        ?string $lastmod = null,
        ?string $priority = null,
        ?string $freq = null,
        array $images = [],
        ?string $title = null,
        array $translations = [],
        array $videos = [],
        array $googlenews = [],
        array $alternates = []
    ): void {
        $params = [
            'loc' => $loc,
            'lastmod' => $lastmod,
            'priority' => $priority,
            'freq' => $freq,
            'images' => $images,
            'title' => $title,
            'translations' => $translations,
            'videos' => $videos,
            'googlenews' => $googlenews,
            'alternates' => $alternates,
        ];

        $this->addItem($params);
    }

    /**
     * Add new sitemap one or multiple items to $items array.
     *
     * @param mixed[] $params
     */
    public function addItem(array $params = []): void
    {
        // if is multidimensional
        if (array_key_exists(1, $params)) {
            foreach ($params as $a) {
                $this->addItem($a);
            }

            return;
        }

        // set default values
        $loc = $params['loc'] ?? '/';

        if (!is_string($loc)) {
            $loc = '/';
        }

        $lastmod = $params['lastmod'] ?? null;
        $priority = $params['priority'] ?? null;
        $freq = $params['freq'] ?? null;
        $title = $params['title'] ?? null;
        $images = $params['images'] ?? [];
        $translations = $params['translations'] ?? [];
        $alternates = $params['alternates'] ?? [];
        $videos = $params['videos'] ?? [];
        $googlenews = $params['googlenews'] ?? [];

        // escaping
        if ($this->model->getEscaping()) {
            $loc = htmlentities($loc, ENT_XML1);

            if ($images) {
                foreach ($images as $k => $image) {
                    foreach ($image as $key => $value) {
                        $images[$k][$key] = htmlentities($value, ENT_XML1);
                    }
                }
            }

            if ($translations) {
                foreach ($translations as $k => $translation) {
                    foreach ($translation as $key => $value) {
                        $translations[$k][$key] = htmlentities($value, ENT_XML1);
                    }
                }
            }

            if ($alternates) {
                foreach ($alternates as $k => $alternate) {
                    foreach ($alternate as $key => $value) {
                        $alternates[$k][$key] = htmlentities($value, ENT_XML1);
                    }
                }
            }

            if ($videos) {
                foreach ($videos as $k => $video) {
                    if (!empty($video['title'])) {
                        $videos[$k]['title'] = htmlentities($video['title'], ENT_XML1);
                    }
                    if (!empty($video['description'])) {
                        $videos[$k]['description'] = htmlentities($video['description'], ENT_XML1);
                    }
                }
            }

            if ($googlenews) {
                if (isset($googlenews['sitename'])) {
                    $googlenews['sitename'] = htmlentities($googlenews['sitename'], ENT_XML1);
                }
            }
        }

        $googlenews['sitename'] = $googlenews['sitename'] ?? '';
        $googlenews['language'] = $googlenews['language'] ?? 'en';
        $googlenews['publication_date'] = $googlenews['publication_date'] ?? date(
            'Y-m-d H:i:s'
        );

        $this->model->setItems([
            'loc' => $loc,
            'lastmod' => $lastmod,
            'priority' => $priority,
            'freq' => $freq,
            'images' => $images,
            'title' => $title,
            'translations' => $translations,
            'videos' => $videos,
            'googlenews' => $googlenews,
            'alternates' => $alternates,
        ]);
    }

    /**
     * Returns document with all sitemap items from $items array.
     *
     * @param string $format (options: xml, html, txt, ror-rss, ror-rdf, google-news)
     * @param ?string $style (path to custom xls style like '/styles/xsl/xml-sitemap.xsl')
     */
    public function render(string $format = 'xml', ?string $style = null): Response
    {
        // limit size of sitemap
        if ($this->model->getMaxSize() > 0 && count($this->model->getItems()) > $this->model->getMaxSize()) {
            $this->model->limitSize($this->model->getMaxSize());
        } elseif ('google-news' == $format && count($this->model->getItems()) > 1000) {
            $this->model->limitSize(1000);
        } elseif ('google-news' != $format && count($this->model->getItems()) > 50000) {
            $this->model->limitSize();
        }

        $data = $this->generate($format);

        return $this->response->make($data['content'], 200, $data['headers']);
    }

    /**
     * Generates document with all sitemap items from $items array.
     *
     * @param string $format (options: xml, html, txt, ror-rss, ror-rdf, sitemapindex, google-news)
     *
     * @return array{
     *     content: string,
     *     headers: array<string, string>
     * }
     */
    public function generate(string $format = 'xml'): array
    {
        // check if caching is enabled, there is a cached content and its duration isn't expired
        if ($this->isCached()) {
            $sitemapOrItems = $this->cache->get(
                $this->model->getCacheKey()
            );

            if (is_array($sitemapOrItems)) {
                if ($format === self::FORMAT_SITEMAP_INDEX) {
                    $this->model->resetSitemaps($sitemapOrItems);
                } else {
                    $this->model->resetItems($sitemapOrItems);
                }
            }
        } elseif ($this->model->getUseCache()) {
            if ($format === self::FORMAT_SITEMAP_INDEX) {
                $this->cache->put(
                    $this->model->getCacheKey(),
                    $this->model->getSitemaps(),
                    $this->model->getCacheDuration()
                );
            } else {
                $this->cache->put(
                    $this->model->getCacheKey(),
                    $this->model->getItems(),
                    $this->model->getCacheDuration()
                );
            }
        }

        if (!$this->model->getLink()) {
            $url = $this->configRepository->get('app.url');

            if (is_string($url) || $url instanceof Stringable) {
                $this->model->setLink((string)$url);
            }
        }

        if (!$this->model->getTitle()) {
            $this->model->setTitle('Sitemap for ' . $this->model->getLink());
        }

        $channel = [
            'title' => $this->model->getTitle(),
            'link' => $this->model->getLink(),
        ];

        // check if styles are enabled
        if ($this->model->getUseStyles()) {
            if (null != $this->model->getSloc() && file_exists(
                    public_path($this->model->getSloc() . $format . '.xsl')
                )) {
                // use style from your custom location
                $style = $this->model->getSloc() . $format . '.xsl';
            } else {
                // don't use style
                $style = null;
            }
        } else {
            // don't use style
            $style = null;
        }

        switch ($format) {
            case 'ror-rss':
                return [
                    'content' => $this->view->make(
                        'sitemap::ror-rss',
                        [
                            'items' => $this->model->getItems(),
                            'channel' => $channel,
                            'style' => $style
                        ]
                    )
                        ->render(),
                    'headers' => ['Content-type' => 'text/rss+xml; charset=utf-8']
                ];
            case 'ror-rdf':
                return [
                    'content' => $this->view->make(
                        'sitemap::ror-rdf',
                        [
                            'items' => $this->model->getItems(),
                            'channel' => $channel,
                            'style' => $style
                        ]
                    )
                        ->render(),
                    'headers' => ['Content-type' => 'text/rdf+xml; charset=utf-8']
                ];
            case 'html':
                return [
                    'content' => $this->view->make(
                        'sitemap::html',
                        [
                            'items' => $this->model->getItems(),
                            'channel' => $channel,
                            'style' => $style
                        ]
                    )
                        ->render(),
                    'headers' => ['Content-type' => 'text/html; charset=utf-8']
                ];
            case 'txt':
                return [
                    'content' => $this->view->make(
                        'sitemap::txt',
                        [
                            'items' => $this->model->getItems(),
                            'style' => $style
                        ]
                    )
                        ->render(),
                    'headers' => ['Content-type' => 'text/plain; charset=utf-8']
                ];
            case self::FORMAT_SITEMAP_INDEX:
                return [
                    'content' => $this->view->make(
                        'sitemap::sitemapindex',
                        [
                            'sitemaps' => $this->model->getSitemaps(),
                            'style' => $style
                        ]
                    )
                        ->render(),
                    'headers' => ['Content-type' => 'text/xml; charset=utf-8']
                ];
            default:
                return [
                    'content' => $this->view->make(
                        'sitemap::' . $format,
                        [
                            'items' => $this->model->getItems(),
                            'style' => $style
                        ]
                    )
                        ->render(),
                    'headers' => ['Content-type' => 'text/xml; charset=utf-8']
                ];
        }
    }

    /**
     * Checks if content is cached.
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function isCached(): bool
    {
        if ($this->model->getUseCache()) {
            if ($this->cache->has($this->model->getCacheKey())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add new sitemap to $sitemaps array.
     *
     * @param Sitemap[] $sitemaps
     */
    public function resetSitemaps(array $sitemaps = []): void
    {
        $this->model->resetSitemaps($sitemaps);
    }

    /**
     * Generate sitemap and store it to a file.
     *
     * @param string $format (options: xml, html, txt, ror-rss, ror-rdf, sitemapindex, google-news)
     * @param string $filename (without file extension, may be a path like 'sitemaps/sitemap1' but must exist)
     * @param ?string $path (path to store sitemap like '/www/site/public')
     * @param ?string $style (path to custom xls style like '/styles/xsl/xml-sitemap.xsl')
     *
     * @return void
     */
    public function store(
        string $format = 'xml',
        string $filename = 'sitemap',
        ?string $path = null,
        ?string $style = null
    ): void {
        // turn off caching for this method
        $this->model->setUseCache(false);

        // use correct file extension
        (in_array(
            $format,
            [
                'txt',
                'html'
            ],
            true
        )) ? $fe = $format : $fe = 'xml';

        if ($this->model->getUseGzip()) {
            $fe = $fe . ".gz";
        }

        // use custom size limit for sitemaps
        if ($this->model->getMaxSize() > 0 && count($this->model->getItems()) > $this->model->getMaxSize()) {
            if ($this->model->getUseLimitSize()) {
                // limit size
                $this->model->limitSize($this->model->getMaxSize());
                $data = $this->generate($format);
            } else {
                // use sitemapindex and generate partial sitemaps
                foreach (array_chunk($this->model->getItems(), $this->model->getMaxSize()) as $key => $item) {
                    // reset current items
                    $this->model->resetItems($item);

                    // generate new partial sitemap
                    $this->store($format, $filename . '-' . $key, $path, $style);

                    // add sitemap to sitemapindex
                    if ($path != null) {
                        // if using custom path generate relative urls for sitemaps in the sitemapindex
                        $this->addSitemap($filename . '-' . $key . '.' . $fe);
                    } else {
                        // else generate full urls based on app's domain
                        $this->addSitemap(url($filename . '-' . $key . '.' . $fe));
                    }
                }

                $data = $this->generate(self::FORMAT_SITEMAP_INDEX);
            }
        } elseif (('google-news' != $format && count(
                    $this->model->getItems()
                ) > 50000) || ($format == 'google-news' && count($this->model->getItems()) > 1000)) {
            ('google-news' != $format) ? $max = 50000 : $max = 1000;

            // check if limiting size of items array is enabled
            if (!$this->model->getUseLimitSize()) {
                // use sitemapindex and generate partial sitemaps
                foreach (array_chunk($this->model->getItems(), $max) as $key => $item) {
                    // reset current items
                    $this->model->resetItems($item);

                    // generate new partial sitemap
                    $this->store($format, $filename . '-' . $key, $path, $style);

                    // add sitemap to sitemapindex
                    if (null != $path) {
                        // if using custom path generate relative urls for sitemaps in the sitemapindex
                        $this->addSitemap($filename . '-' . $key . '.' . $fe);
                    } else {
                        // else generate full urls based on app's domain
                        $this->addSitemap(url($filename . '-' . $key . '.' . $fe));
                    }
                }

                $data = $this->generate(self::FORMAT_SITEMAP_INDEX);
            } else {
                // reset items and use only most recent $max items
                $this->model->limitSize($max);
                $data = $this->generate($format);
            }
        } else {
            $data = $this->generate($format);
        }

        // clear memory
        if (self::FORMAT_SITEMAP_INDEX == $format) {
            $this->model->resetSitemaps();
        }

        $this->model->resetItems();

        if ($path === null) {
            $path = public_path();
        }

        $file = $path . DIRECTORY_SEPARATOR . $filename . '.' . $fe;

        $this->writeViewToDisk($file, $data['content']);
    }

    private function writeViewToDisk(string $file, string $content): void
    {
        if ($this->model->getUseGzip()) {
            $compressed = gzencode($content, 9);

            if ($compressed === false) {
                throw new RuntimeException('Failed to compress view');
            }

            $content = $compressed;
        }

        $this->file->put($file, $content);
    }

    /**
     * Add new sitemap to $sitemaps array.
     */
    public function addSitemap(string $loc, ?string $lastmod = null): void
    {
        $this->model->setSitemaps([
            'loc' => $loc,
            'lastmod' => $lastmod,
        ]);
    }
}
