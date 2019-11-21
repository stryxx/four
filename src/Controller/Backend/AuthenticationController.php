<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Bolt\Controller\TwigAwareController;
use Cocur\Slugify\Slugify;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthenticationController extends TwigAwareController implements BackendZone
{
    /**
     * @Route("/login", name="bolt_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $slugify = new Slugify();

        // last username entered by the user (if any)
        $last_username = $slugify->slugify($authenticationUtils->getLastUsername());

        // last authentication error (if any)
        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->renderTemplate('@bolt/security/login.html.twig', [
            'last_username' => $last_username,
            'error' => $error,
        ]);
    }

    /**
     * This is the route the user can use to logout.
     *
     * But, this will never be executed. Symfony will intercept this first
     * and handle the logout automatically. See logout in config/packages/security.yaml
     *
     * @Route("/logout", name="bolt_logout")
     */
    public function logout(): void
    {
        throw new \Exception('This should never be reached!');
    }

    /**
     * @Route("/resetpassword", name="bolt_resetpassword")
     */
    public function resetPassword(): Response
    {
        $twigVars = [
            'title' => 'controller.authentication.reset_title',
            'subtitle' => 'controller.authentication.reset_subtitle',
        ];

        return $this->renderTemplate('@bolt/pages/placeholder.html.twig', $twigVars);
    }
}
