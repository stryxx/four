<?php

declare(strict_types=1);

namespace Bolt\Controller\Frontend;

use Bolt\Configuration\Content\ContentType;
use Bolt\Controller\TwigAwareController;
use Bolt\Entity\Content;
use Bolt\Repository\ContentRepository;
use Bolt\TemplateChooser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ListingController extends TwigAwareController implements FrontendZone
{
    /** @var TemplateChooser */
    private $templateChooser;

    public function __construct(TemplateChooser $templateChooser)
    {
        $this->templateChooser = $templateChooser;
    }

    /**
     * @Route(
     *     "/{contentTypeSlug}",
     *     name="listing",
     *     requirements={"contentTypeSlug"="%bolt.requirement.contenttypes%"},
     *     methods={"GET"})
     * @Route(
     *     "/{_locale}/{contentTypeSlug}",
     *     name="listing_locale",
     *     requirements={"contentTypeSlug"="%bolt.requirement.contenttypes%", "_locale": "%app_locales%"},
     *     methods={"GET"})
     */
    public function listing(ContentRepository $contentRepository, Request $request, string $contentTypeSlug): Response
    {
        $contentType = ContentType::factory($contentTypeSlug, $this->config->get('contenttypes'));

        $page = (int) $request->query->get('page', 1);
        $amountPerPage = $contentType->get('listing_records');

        /** @var Content[] $records */
        $records = $contentRepository->findForListing($page, $amountPerPage, $contentType);

        $templates = $this->templateChooser->forListing($contentType);

        $twigVars = [
            'records' => $records,
            $contentType->getSlug() => $records,
            'contenttype' => $contentType,
        ];

        return $this->renderTemplate($templates, $twigVars);
    }
}
