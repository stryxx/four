<?php

declare(strict_types=1);

namespace Bolt\Configuration;

use Bolt\Collection\DeepCollection;
use Bolt\Common\Arr;
use Bolt\Configuration\Parser\BaseParser;
use Bolt\Configuration\Parser\ContentTypesParser;
use Bolt\Configuration\Parser\GeneralParser;
use Bolt\Configuration\Parser\MenuParser;
use Bolt\Configuration\Parser\TaxonomyParser;
use Bolt\Configuration\Parser\ThemeParser;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Cache\CacheInterface;
use Tightenco\Collect\Support\Collection;

class Config
{
    /** @var Collection */
    protected $data;

    /** @var PathResolver */
    private $pathResolver;

    /** @var Stopwatch */
    private $stopwatch;

    /** @var CacheInterface */
    private $cache;

    /** @var string */
    private $projectDir;

    public function __construct(Stopwatch $stopwatch, string $projectDir, CacheInterface $cache)
    {
        $this->stopwatch = $stopwatch;
        $this->cache = $cache;
        $this->projectDir = $projectDir;
        $this->data = $this->getConfig();

        // @todo PathResolver shouldn't be part of Config. Refactor to separate class
        $this->pathResolver = new PathResolver($projectDir, [], $this->get('general/theme'));
    }

    private function getConfig(): Collection
    {
        $this->stopwatch->start('bolt.parseconfig');

        [$data, $timestamps] = $this->getCache();

        // Verify if timestamps are unchanged. If not, invalidate cache.
        foreach ($timestamps as $filename => $timestamp) {
            if (file_exists($filename) === false || filemtime($filename) > $timestamp) {
                $this->cache->delete('config_cache');
                [$data] = $this->getCache();
            }
        }

        $this->stopwatch->stop('bolt.parseconfig');

        return $data;
    }

    private function getCache(): array
    {
        return $this->cache->get('config_cache', function () {
            return $this->parseConfig();
        });
    }

    /**
     * Load the configuration from the various YML files.
     */
    private function parseConfig(): array
    {
        $general = new GeneralParser($this->projectDir);

        $config = new Collection([
            'general' => $general->parse(),
        ]);

        $taxonomy = new TaxonomyParser($this->projectDir);
        $config['taxonomies'] = $taxonomy->parse();

        $contentTypes = new ContentTypesParser($this->projectDir, $config->get('general'));
        $config['contenttypes'] = $contentTypes->parse();

        $menu = new MenuParser($this->projectDir);
        $config['menu'] = $menu->parse();

        // If we're parsing the config, we'll also need to pre-initialise
        // the PathResolver, because we need to know the theme path.
        $this->pathResolver = new PathResolver($this->projectDir, [], $config->get('general')->get('theme'));

        $theme = new ThemeParser($this->projectDir, $this->getPath('theme'));
        $config['theme'] = $theme->parse();

        // @todo Add these config files if needed, or refactor them out otherwise
        //'permissions' => $this->parseConfigYaml('permissions.yml'),
        //'extensions' => $this->parseConfigYaml('extensions.yml'),

        $timestamps = $this->getConfigFilesTimestamps($general, $taxonomy, $contentTypes, $menu, $theme);

        return [
            DeepCollection::deepMake($config),
            $timestamps,
        ];
    }

    private function getConfigFilesTimestamps(BaseParser ...$configs): array
    {
        $timestamps = [];

        foreach ($configs as $config) {
            foreach ($config->getParsedFilenames() as $file) {
                $timestamps[$file] = filemtime($file);
            }
        }

        $envFilename = $this->projectDir . '/.env';
        if (file_exists($envFilename)) {
            $timestamps[$envFilename] = filemtime($envFilename);
        }

        return $timestamps;
    }

    /**
     * Get a config value, using a path.
     *
     * For example:
     * $var = $config->get('general/wysiwyg/ck/contentsCss');
     *
     * @param string|array|bool|int $default
     */
    public function get(string $path, $default = null)
    {
        $value = Arr::get($this->data, $path, $default);

        // Basic getenv parser, for values like `%env(FOO_BAR)%`
        if (is_string($value) && preg_match('/%env\(([A-Z0-9_]+)\)%/', $value, $matches)) {
            if (getenv($matches[1])) {
                $value = getenv($matches[1]);
            }
        }

        return $value;
    }

    public function has(string $path): bool
    {
        return Arr::has($this->data, $path);
    }

    public function getPath(string $path, bool $absolute = true, $additional = null): string
    {
        return $this->pathResolver->resolve($path, $absolute, $additional);
    }

    public function getPaths(): Collection
    {
        return $this->pathResolver->resolveAll();
    }

    public function getMediaTypes(): Collection
    {
        return new Collection($this->get('general/accept_media_types'));
    }

    public function getContentType(string $name): ?Collection
    {
        return $this->get('contenttypes/' . $name);
    }

    public function getFileTypes(): Collection
    {
        return new Collection($this->get('general/accept_file_types'));
    }
}
