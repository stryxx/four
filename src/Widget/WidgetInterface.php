<?php

declare(strict_types=1);

namespace Bolt\Widget;

use Bolt\Extension\ExtensionInterface;

/**
 * Every widget must implement this interface.
 */
interface WidgetInterface
{
    public function getName(): string;

    /**
     * @return string from Bolt\Widget\Injector\Target constants enum
     */
    public function getTarget(): string;

    public function getPriority(): int;

    /**
     * @return string from Bolt\Widget\Injector\RequestZone constants enum
     */
    public function getZone(): string;

    public function __invoke(array $params = []): ?string;

    public function injectExtension(ExtensionInterface $extension): void;
}
