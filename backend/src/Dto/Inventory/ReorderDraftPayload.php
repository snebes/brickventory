<?php

declare(strict_types=1);

namespace App\Dto\Inventory;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReorderDraftPayload
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Uuid] public string $request_id,
        #[Assert\Count(min: 1, max: 100)] #[Assert\Unique] #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])] public array $product_ids,
        #[Assert\Count(max: 100)] #[Assert\All([new Assert\Type('string'), new Assert\Regex('/^[a-f0-9]{64}$/D')])] public array $expected = [],
    ) {
    }
}
