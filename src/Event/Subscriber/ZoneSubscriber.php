<?php

declare(strict_types=1);

namespace Bolt\Event\Subscriber;

use Bolt\Controller\Backend\Async\AsyncZone;
use Bolt\Controller\Backend\BackendZone;
use Bolt\Controller\Frontend\FrontendZone;
use Bolt\Widget\Injector\RequestZone;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ZoneSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 31;

    /**
     * Kernel request listener callback.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (RequestZone::getFromRequest($request) !== RequestZone::NOWHERE) {
            return;
        }

        $this->setZone($request);
    }

    /**
     * Sets the request's zone if needed and returns it.
     */
    public function setZone(Request $request): string
    {
        if (RequestZone::getFromRequest($request) !== RequestZone::NOWHERE) {
            return RequestZone::getFromRequest($request);
        }

        $zone = $this->determineZone($request);
        RequestZone::setToRequest($request, $zone);

        return $zone;
    }

    /**
     * Determine the zone and return it.
     */
    protected function determineZone(Request $request): string
    {
        if ($request->isXmlHttpRequest()) {
            return RequestZone::ASYNC;
        }

        $controller = explode('::', $request->attributes->get('_controller'));

        try {
            $reflection = new ReflectionClass($controller[0]);

            if ($reflection->implementsInterface(BackendZone::class)) {
                return RequestZone::BACKEND;
            } elseif ($reflection->implementsInterface(FrontendZone::class)) {
                return RequestZone::FRONTEND;
            } elseif ($reflection->implementsInterface(AsyncZone::class)) {
                return RequestZone::ASYNC;
            }
        } catch (\ReflectionException $e) {
            // Alas..
        }

        return RequestZone::NOWHERE;
    }

    /**
     * Return the events to subscribe to.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', self::PRIORITY]], // Right after route is matched
        ];
    }
}
