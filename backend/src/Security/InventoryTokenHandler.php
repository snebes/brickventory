<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class InventoryTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        #[Autowire(env: 'INVENTORY_API_TOKEN')]
        #[\SensitiveParameter]
        private string $token,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        if ('' === $this->token || !hash_equals($this->token, $accessToken)) {
            throw new BadCredentialsException('Invalid API token.');
        }

        return new UserBadge('inventory-api');
    }
}
