<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\AdminBundle\Security;

use JetBrains\PhpStorm\ArrayShape;
use Pimcore\Event\AdminEvents;
use Pimcore\Event\Admin\Login\LogoutEvent as PimcoreLogoutEvent;
use Pimcore\Model\Element\Editlock;
use Pimcore\Model\User;
use Pimcore\Tool\Session;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Handle logout. This was originally implemented as LogoutHandler, but wasn't triggered as the token was empty at call
 * time in LogoutListener::handle was called. As the logout success handler is always triggered it is now implemented as
 * success handler.
 *
 *
 * @internal
 */
class LogoutSuccessListener implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    /**
     * @param TokenStorageInterface $tokenStorage
     * @param RouterInterface $router
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected RouterInterface $router,
        protected EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * @param LogoutEvent $event
     *
     * @return mixed
     */
    public function onLogout(LogoutEvent $event): mixed
    {
        $request = $event->getRequest();

        $this->logger->debug('Logging out');

        $this->tokenStorage->setToken(null);

        // clear open edit locks for this session
        Editlock::clearSession(Session::getSessionId());

        /** @var LogoutEvent|null $event */
        $event = Session::useSession(function (AttributeBagInterface $adminSession) use ($request) {
            $event = null;

            $user = $adminSession->get('user');
            if ($user && $user instanceof User) {
                $event = new PimcoreLogoutEvent($request, $user);
                $this->eventDispatcher->dispatch($event, AdminEvents::LOGIN_LOGOUT);

                $adminSession->remove('user');
            }

            Session::invalidate();

            return $event;
        });

        if ($event && $event->hasResponse()) {
            $response = $event->getResponse();
        } else {
            $response = new RedirectResponse($this->router->generate('pimcore_admin_index'));
        }

        // cleanup pimcore-cookies => 315554400 => strtotime('1980-01-01')
        $response->headers->setCookie(new Cookie('pimcore_opentabs', false, 315554400, '/'));
        $response->headers->clearCookie('pimcore_admin_sid', '/', null, false, true);

        if ($response instanceof RedirectResponse) {
            $this->logger->debug('Logout succeeded, redirecting to ' . $response->getTargetUrl());
        }

        return $response;
    }
}