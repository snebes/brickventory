<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add reference field to purchase_order and rate field to purchase_order_line
 */
final class Version20260101221000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reference field to purchase_order table and rate field to purchase_order_line table';
    }

    public function up(Schema $schema): void
    {
        // Add reference field to purchase_order
        $this->addSql('ALTER TABLE purchase_order ADD reference VARCHAR(255) DEFAULT NULL');
        
        // Add rate field to purchase_order_line
        $this->addSql('ALTER TABLE purchase_order_line ADD rate NUMERIC(10, 2) NOT NULL DEFAULT 0.0');
    }

    public function down(Schema $schema): void
    {
        // Remove reference field from purchase_order
        $this->addSql('ALTER TABLE purchase_order DROP reference');
        
        // Remove rate field from purchase_order_line
        $this->addSql('ALTER TABLE purchase_order_line DROP rate');
    }
}
