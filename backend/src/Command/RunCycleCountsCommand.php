<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Command;

use App\Inventory\StockCounts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'inventory:run-cycle-counts', description: 'Create draft stock audits for due cycle-count schedules.')]
final class RunCycleCountsCommand extends Command
{
    public function __construct(private readonly StockCounts $counts)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = false;
        foreach ($this->counts->schedules() as $schedule) {
            try {
                $result = $this->counts->run($schedule['id'], true);
                $output->writeln(sprintf('Schedule %d: %d draft counts created.', $schedule['id'], count($result['created'])));
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>Schedule %d failed: %s</error>', $schedule['id'], $e->getMessage()));
                $failed = true;
                break;
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
