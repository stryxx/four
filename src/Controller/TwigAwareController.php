<?php

declare(strict_types=1);

namespace Bolt\Controller;

use Bolt\Configuration\Config;
use Bolt\Entity\Field\TemplateselectField;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\TwigBundle\Loader\NativeFilesystemLoader;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Response;
use Tightenco\Collect\Support\Collection;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigAwareController extends AbstractController
{
    /** @var Config */
    protected $config;

    /** @var Environment */
    protected $twig;

    /** @var Packages */
    protected $packages;

    /**
     * @required
     */
    public function setAutowire(Config $config, Environment $twig, Packages $packages): void
    {
        $this->config = $config;
        $this->twig = $twig;
        $this->packages = $packages;
    }

    /**
     * Renders a view.
     *
     * @final
     *
     * @param string|array $template
     *
     * @throws \Twig_Error_Loader  When none of the templates can be found
     * @throws \Twig_Error_Syntax  When an error occurred during compilation
     * @throws \Twig_Error_Runtime When an error occurred during rendering
     */
    protected function renderTemplate($template, array $parameters = [], ?Response $response = null): Response
    {
        // Set User in global Twig environment
        $parameters['user'] = $parameters['user'] ?? $this->getUser();

        // if theme.yaml was loaded, set it as global.
        if ($this->config->has('theme')) {
            $parameters['theme'] = $this->config->get('theme');
        }

        $this->setThemePackage();
        $this->setTwigLoader();

        // Resolve string|array of templates into the first one that is found.
        if (is_array($template)) {
            $templates = (new Collection($template))
                ->map(function ($element): ?string {
                    if ($element instanceof TemplateselectField) {
                        return $element->__toString();
                    }
                    return $element;
                })
                ->filter()
                ->toArray();
            $template = $this->twig->resolveTemplate($templates);
        }

        // Render the template
        $content = $this->twig->render($template, $parameters);

        // Make sure we have a Response
        if ($response === null) {
            $response = new Response();
        }
        $response->setContent($content);

        return $response;
    }

    private function setTwigLoader(): void
    {
        /** @var NativeFilesystemLoader $twigLoaders */
        $twigLoaders = $this->twig->getLoader();

        if ($twigLoaders instanceof FilesystemLoader) {
            $twigLoaders->prependPath($this->config->getPath('theme'), '__main__');
        }
    }

    private function setThemePackage(): void
    {
        // get the default package, and re-add as `bolt`
        $boltPackage = $this->packages->getPackage();
        $this->packages->addPackage('bolt', $boltPackage);

        // set `theme` package, and also as 'default'
        $themePath = '/theme/' . $this->config->get('general/theme');
        $themePackage = new PathPackage($themePath, new EmptyVersionStrategy());
        $this->packages->setDefaultPackage($themePackage);
        $this->packages->addPackage('theme', $themePackage);

        // set `public` package
        $publicPackage = new PathPackage('/', new EmptyVersionStrategy());
        $this->packages->addPackage('public', $publicPackage);

        // set `files` package
        $filesPackage = new PathPackage('/files/', new EmptyVersionStrategy());
        $this->packages->addPackage('files', $filesPackage);
    }
}
