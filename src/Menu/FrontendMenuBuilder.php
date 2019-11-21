<?php

declare(strict_types=1);

namespace Bolt\Menu;

use Bolt\Collection\DeepCollection;
use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Repository\ContentRepository;
use Bolt\Twig\ContentExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class FrontendMenuBuilder implements FrontendMenuBuilderInterface
{
    /** @var Config */
    private $config;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var ContentRepository */
    private $contentRepository;

    /** @var ContentExtension */
    private $contentExtension;

    public function __construct(
        Config $config,
        UrlGeneratorInterface $urlGenerator,
        ContentRepository $contentRepository,
        ContentExtension $contentExtension
    ) {
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->contentRepository = $contentRepository;
        $this->contentExtension = $contentExtension;
    }

    public function buildMenu(?string $name = null): array
    {
        /** @var DeepCollection $menuConfig */
        $menuConfig = $this->config->get('menu');

        if ($name === null) {
            $menu = $menuConfig->first()->toArray();
        } elseif ($name !== '' && isset($menuConfig[$name])) {
            $menu = $menuConfig[$name]->toArray();
        } else {
            throw new \RuntimeException("Tried to build non-existing menu: {$name}");
        }

        $menu = array_map(function ($item): array {
            return $this->setUris($item);
        }, $menu);

        return $menu;
    }

    private function setUris(array $item): array
    {
        [$title, $item['uri']] = $this->generateUri($item['link']);

        if (empty($item['title'])) {
            $item['title'] = $title;
        }

        if (is_iterable($item['submenu'])) {
            $item['submenu'] = array_map(function ($sub): array {
                return $this->setUris($sub);
            }, $item['submenu']);
        }

        return $item;
    }

    private function generateUri(string $link = ''): array
    {
        $trimmedLink = trim($link, '/');

        // Special case for "Homepage"
        if ($trimmedLink === 'homepage') {
            return ['Home', $this->urlGenerator->generate('homepage')];
        }

        // If it looks like `contenttype/slug`, get the Record.
        if (preg_match('/^[a-zA-Z\-\_]+\/[0-9a-zA-Z\-\_]+$/', $trimmedLink)) {
            $content = $this->getContent($trimmedLink);
            if ($content) {
                return [$this->contentExtension->getTitle($content), $this->contentExtension->getLink($content)];
            }
        }

        // Otherwise trust the user. ¯\_(ツ)_/¯
        return ['', $link];
    }

    private function getContent(string $link): ?Content
    {
        $parts = explode('/', $link);

        // First, try to get it if the id is numeric.
        if (is_numeric($parts[1])) {
            return $this->contentRepository->findOneById((int) $parts[1]);
        }

        // Otherwise fetch it by getting it from the slug
        // @todo it should check content type slug too
        return $this->contentRepository->findOneBySlug($parts[1]);
    }
}
