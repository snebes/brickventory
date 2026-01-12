<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add inventory_adjustment and inventory_adjustment_line tables for inventory adjustments
 */
final class Version20260112030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create inventory_adjustment and inventory_adjustment_line tables for manual inventory adjustments';
    }

    public function up(Schema $schema): void
    {
        // Create inventory_adjustment table
        $this->addSql('CREATE TABLE inventory_adjustment (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            adjustment_number VARCHAR(50) UNIQUE NOT NULL,
            adjustment_date TIMESTAMP NOT NULL,
            reason VARCHAR(50) NOT NULL,
            memo TEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'approved\'
        )');

        // Create inventory_adjustment_line table
        $this->addSql('CREATE TABLE inventory_adjustment_line (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            inventory_adjustment_id INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            quantity_change INTEGER NOT NULL,
            notes TEXT DEFAULT NULL,
            CONSTRAINT fk_inv_adj_line_adjustment FOREIGN KEY (inventory_adjustment_id) REFERENCES inventory_adjustment(id) ON DELETE CASCADE,
            CONSTRAINT fk_inv_adj_line_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT
        )');

        // Create indexes for efficient querying
        $this->addSql('CREATE INDEX idx_inv_adj_line_adjustment ON inventory_adjustment_line (inventory_adjustment_id)');
        $this->addSql('CREATE INDEX idx_inv_adj_line_item ON inventory_adjustment_line (item_id)');
        $this->addSql('CREATE INDEX idx_inv_adj_date ON inventory_adjustment (adjustment_date)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order due to foreign keys
        $this->addSql('DROP TABLE inventory_adjustment_line');
        $this->addSql('DROP TABLE inventory_adjustment');
    }
}
