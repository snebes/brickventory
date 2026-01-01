<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add quantity_committed field to item table
 */
final class Version20260101233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quantity_committed field to item table';
    }

    public function up(Schema $schema): void
    {
        // Add quantity_committed column to item table
        $this->addSql('ALTER TABLE item ADD COLUMN quantity_committed INTEGER NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // Remove quantity_committed column from item table
        $this->addSql('ALTER TABLE item DROP COLUMN quantity_committed');
    }
}
