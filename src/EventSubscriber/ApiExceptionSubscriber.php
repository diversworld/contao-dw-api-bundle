<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 256],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        if (!$exception instanceof AccessDeniedException && !$exception instanceof AuthenticationException) {
            return;
        }

        // API clients expect JSON, while Contao's default access denied handling
        // renders an HTML error page for frontend requests.
        $statusCode = $this->security->getUser() === null || $exception instanceof AuthenticationException ? 401 : 403;
        $event->setResponse(new JsonResponse([
            'error' => $statusCode === 401 ? 'Unauthorized' : 'Forbidden',
        ], $statusCode));
    }
}
