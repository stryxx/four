<?php

declare(strict_types=1);

namespace Bolt\Controller\Frontend;

use Bolt\Controller\TwigAwareController;
use Bolt\Entity\Content;
use Bolt\Repository\ContentRepository;
use Bolt\TemplateChooser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TaxonomyController extends TwigAwareController implements FrontendZone
{
    /** @var TemplateChooser */
    private $templateChooser;

    public function __construct(TemplateChooser $templateChooser)
    {
        $this->templateChooser = $templateChooser;
    }

    /**
     * @Route(
     *     "/{taxonomyslug}/{slug}",
     *     name="taxonomy",
     *     requirements={"taxonomyslug"="%bolt.requirement.taxonomies%"},
     *     methods={"GET"}
     * )
     * @Route(
     *     "/{_locale}/{taxonomyslug}/{slug}",
     *     name="taxonomy_locale",
     *     requirements={"taxonomyslug"="%bolt.requirement.taxonomies%", "_locale": "%app_locales%"},
     *     methods={"GET"}
     * )
     */
    public function listing(ContentRepository $contentRepository, Request $request, string $taxonomyslug, string $slug): Response
    {
        $page = (int) $request->query->get('page', 1);
        $amountPerPage = $this->config->get('general/listing_records');

        $taxonomy = $this->config->get('taxonomies/'. $taxonomyslug);

        /** @var Content[] $records */
        $records = $contentRepository->findForTaxonomy($page, $taxonomy, $slug, $amountPerPage);

        $templates = $this->templateChooser->forTaxonomy($taxonomyslug);

        $twigVars = [
            'records' => $records,
            'taxonomy' => $taxonomy,
            'slug' => $slug,
        ];

        return $this->renderTemplate($templates, $twigVars);
    }
}
