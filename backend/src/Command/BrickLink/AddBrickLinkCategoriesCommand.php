<?php

declare(strict_types=1);

namespace App\Command\BrickLink;

use App\Message\BrickLink\AddBrickLinkCategoriesMessage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:bricklink:categories', description: 'Add/Update the BrickLink category data')]
class AddBrickLinkCategoriesCommand
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(
        #[Argument('BrickLink categories.xml File')]
        string $filename,
        SymfonyStyle $io,
    ): int {
        try {
            $message = new AddBrickLinkCategoriesMessage($filename);
            $this->messageBus->dispatch($message);
        } catch (ExceptionInterface $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
