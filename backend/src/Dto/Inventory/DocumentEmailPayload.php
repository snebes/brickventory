<?php

declare(strict_types=1);

namespace App\Dto\Inventory;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DocumentEmailPayload
{
    public function __construct(
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public ?string $to = null,
        #[Assert\Count(max: 10)]
        #[Assert\All([new Assert\Email(), new Assert\NotBlank(), new Assert\Length(max: 255)])]
        public array $cc = [],
        #[Assert\Length(max: 4000)]
        public string $message = '',
    ) {
    }
}
