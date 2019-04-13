<?php

declare(strict_types=1);

namespace Bolt\EventSubscriber;

use Bolt\Controller\Backend\Async\AsyncControllerInterface;
use Bolt\Controller\Backend\BackendControllerInterface;
use Bolt\Controller\Frontend\FrontendControllerInterface;
use Bolt\Snippet\Zone;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ZoneSubscriber implements EventSubscriberInterface
{
    /**
     * Kernel request listener callback.
     */
    public function onKernelRequest(GetResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (Zone::get($request) !== Zone::NOWHERE) {
            return;
        }

        $this->setZone($request);
    }

    /**
     * Sets the request's zone if needed and returns it.
     */
    public function setZone(Request $request): string
    {
        if (Zone::get($request) !== Zone::NOWHERE) {
            return Zone::get($request);
        }

        $zone = $this->determineZone($request);
        Zone::set($request, $zone);

        return $zone;
    }

    /**
     * Determine the zone and return it.
     */
    protected function determineZone(Request $request): string
    {
        if ($request->isXmlHttpRequest()) {
            return Zone::ASYNC;
        }

        $controller = explode('::', $request->attributes->get('_controller'));

        try {
            $reflection = new ReflectionClass($controller[0]);

            if ($reflection->implementsInterface(BackendControllerInterface::class)) {
                return Zone::BACKEND;
            } elseif ($reflection->implementsInterface(FrontendControllerInterface::class)) {
                return Zone::FRONTEND;
            } elseif ($reflection->implementsInterface(AsyncControllerInterface::class)) {
                return Zone::ASYNC;
            }
        } catch (\ReflectionException $e) {
            // Alas..
        }

        return Zone::NOWHERE;
    }

    /**
     * Return the events to subscribe to.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 31]], // Right after route is matched
        ];
    }
}
