<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Bolt\Controller\TwigAwareController;
use Bolt\Entity\Content;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Security("is_granted('ROLE_ADMIN')")
 */
class ContentLocalizationController extends TwigAwareController implements BackendZone
{
    /**
     * @Route("/edit_locales/{id}", name="bolt_content_edit_locales", methods={"GET"})
     */
    public function locales(Request $request, Content $content): Response
    {
        $content->getFields();

        return $this->renderTemplate('@bolt/content/view_locales.html.twig', [
            'record' => $content,
        ]);
    }
}
