<?php

declare(strict_types=1);

namespace Bolt\Widget;

use Symfony\Contracts\Cache\CacheInterface;

/**
 * Interface CacheAware - Widgets that make use of caching need to implement
 * this interface, in order to have their contents cached.
 */
interface CacheAware extends WidgetInterface
{
    public function setCache(CacheInterface $config): void;
}
