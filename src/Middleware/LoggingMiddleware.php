<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $messageClass = get_class($message);
        
        $this->logger->info('Executing message: {messageClass}', [
            'messageClass' => $messageClass,
            'message' => $message,
        ]);

        $start = microtime(true);

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
            
            $duration = microtime(true) - $start;
            
            $handledStamp = $envelope->last(HandledStamp::class);
            $result = $handledStamp?->getResult();
            
            $this->logger->info('Message executed successfully: {messageClass}', [
                'messageClass' => $messageClass,
                'duration' => round($duration * 1000, 2) . 'ms',
                'result' => is_scalar($result) ? $result : gettype($result),
            ]);

            return $envelope;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            
            $this->logger->error('Message execution failed: {messageClass}', [
                'messageClass' => $messageClass,
                'duration' => round($duration * 1000, 2) . 'ms',
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            throw $e;
        }
    }
}
