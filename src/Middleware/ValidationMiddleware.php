<?php

declare(strict_types=1);

namespace App\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        
        // Validate the message/command/query
        $violations = $this->validator->validate($message);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            
            throw new \InvalidArgumentException(
                'Validation failed: ' . implode(', ', $errors)
            );
        }
        
        return $stack->next()->handle($envelope, $stack);
    }
}
