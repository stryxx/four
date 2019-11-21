<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Security("is_granted('ROLE_ADMIN')")
 */
class ClearCacheController extends AbstractController implements BackendZone
{
    /**
     * @Route("/clearcache", name="bolt_clear_cache")
     */
    public function index(KernelInterface $kernel): Response
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'cache:clear',
            '--no-warmup' => true,
        ]);
        $output = new BufferedOutput();

        $application->run($input, $output);
        $this->addFlash('success', 'label.cache_cleared');

        $twigvars = [
            'output' => $output->fetch(),
        ];

        return $this->render('@bolt/pages/clearcache.html.twig', $twigvars);
    }
}
