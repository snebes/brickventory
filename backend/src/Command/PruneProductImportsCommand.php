<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Command;

use App\Entity\Inventory\ProductImport;
use App\Inventory\ProductImportStorage;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'inventory:imports:prune', description: 'Delete terminal import jobs and private source files older than the retention period.')]
final class PruneProductImportsCommand extends Command
{
    public function __construct(private readonly ManagerRegistry $registry, private readonly ProductImportStorage $storage)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Retention in days (minimum 1)', '7');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]]);
        if (false === $days) {
            $output->writeln('<error>Days must be between 1 and 3650.</error>');

            return Command::INVALID;
        }
        $before = new \DateTimeImmutable('-'.$days.' days');
        $count = 0;
        $em = $this->registry->getManager();
        $ids = $em->getRepository(ProductImport::class)->createQueryBuilder('j')->select('j.id')->where('j.updatedAt < :before')->andWhere('j.status IN (:statuses)')->setParameter('before', $before)->setParameter('statuses', ['completed', 'failed', 'cancelled'])->getQuery()->getSingleColumnResult();
        foreach ($ids as $id) {
            $count += $em->wrapInTransaction(function ($em) use ($id, $before) {
                $job = $em->find(ProductImport::class, $id, LockMode::PESSIMISTIC_WRITE);
                if (!$job || $job->updatedAt >= $before || !in_array($job->status, ['completed', 'failed', 'cancelled'], true)) {
                    return 0;
                }
                $this->storage->delete($id);
                $em->remove($job);

                return 1;
            });
            $em->clear();
        }
        $output->writeln(sprintf('Pruned %d import jobs.', $count));

        return Command::SUCCESS;
    }
}
