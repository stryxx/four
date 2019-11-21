<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Bolt\Controller\TwigAwareController;
use Bolt\Entity\Content;
use Bolt\Repository\ContentRepository;
use Bolt\Version;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Security("is_granted('ROLE_ADMIN')")
 */
class GeneralController extends TwigAwareController implements BackendZone
{
    /** @var \Bolt\Doctrine\Version */
    private $doctrineVersion;

    public function __construct(\Bolt\Doctrine\Version $doctrineVersion)
    {
        $this->doctrineVersion = $doctrineVersion;
    }

    /**
     * @Route("/about", name="bolt_about")
     */
    public function about(): Response
    {
        $twigVars = [
            'installType' => Version::installType(),
            'platform' => $this->doctrineVersion->getPlatform(),
        ];

        return $this->renderTemplate('@bolt/pages/about.html.twig', $twigVars);
    }

    /**
     * @Route("/kitchensink", name="bolt_kitchensink")
     */
    public function kitchensink(ContentRepository $content): Response
    {
        /** @var Content $records */
        $records = $content->findLatest(null, 1, 4);

        $this->addFlash('success', '<strong>Well done!</strong> You successfully read this important alert message.');
        $this->addFlash('info', '<strong>Heads up!</strong> This alert needs your attention, but it\'s not super important.');
        $this->addFlash('warning', '<strong>Warning!</strong> Better check yourself, you\'re not looking too good.');
        $this->addFlash('danger', '<strong>Oh snap!</strong> Change a few things up and try submitting again.');

        $twigVars = [
            'title' => 'Kitchensink',
            'subtitle' => 'To show a number of different things, on one page',
            'records' => $records,
        ];

        return $this->renderTemplate('@bolt/pages/kitchensink.html.twig', $twigVars);
    }
}
