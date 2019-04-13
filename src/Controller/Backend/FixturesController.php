<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Bolt\Controller\TwigAwareController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Security("has_role('ROLE_ADMIN')")
 */
class FixturesController extends TwigAwareController implements BackendControllerInterface
{
    /**
     * @Route("/fixtures", name="bolt_fixtures")
     */
    public function fixtures(): Response
    {
        $twigVars = [
            'title' => 'Fixtures',
            'subtitle' => 'To add Fixtures, or "Dummy Content".',
        ];

        return $this->renderTemplate('@bolt/pages/placeholder.html.twig', $twigVars);
    }
}
