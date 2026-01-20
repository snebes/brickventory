<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create LandedCost and LandedCostAllocation tables for NetSuite ERP workflow
 */
final class Version20260120054000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create LandedCost and LandedCostAllocation tables for landed cost management';
    }

    public function up(Schema $schema): void
    {
        // Create landed_cost table
        $this->addSql('CREATE TABLE landed_cost (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            landed_cost_number VARCHAR(55) UNIQUE NOT NULL,
            item_receipt_id INTEGER NOT NULL,
            vendor_bill_id INTEGER DEFAULT NULL,
            cost_category VARCHAR(50) NOT NULL DEFAULT \'Freight\',
            total_cost DECIMAL(10, 2) NOT NULL,
            allocation_method VARCHAR(50) NOT NULL DEFAULT \'Value\',
            applied_date DATE NOT NULL,
            CONSTRAINT fk_landed_cost_receipt FOREIGN KEY (item_receipt_id) REFERENCES item_receipt(id) ON DELETE RESTRICT
        )');

        // Create indexes for landed_cost
        $this->addSql('CREATE INDEX idx_landed_cost_receipt ON landed_cost (item_receipt_id)');
        $this->addSql('CREATE INDEX idx_landed_cost_bill ON landed_cost (vendor_bill_id)');
        $this->addSql('CREATE INDEX idx_landed_cost_date ON landed_cost (applied_date)');

        // Create landed_cost_allocation table
        $this->addSql('CREATE TABLE landed_cost_allocation (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            landed_cost_id INTEGER NOT NULL,
            receipt_line_id INTEGER NOT NULL,
            cost_layer_id INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            allocated_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            allocation_percentage DECIMAL(5, 4) NOT NULL DEFAULT 0.0,
            quantity DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            original_unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            adjusted_unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            CONSTRAINT fk_lca_landed_cost FOREIGN KEY (landed_cost_id) REFERENCES landed_cost(id) ON DELETE CASCADE,
            CONSTRAINT fk_lca_receipt_line FOREIGN KEY (receipt_line_id) REFERENCES item_receipt_line(id) ON DELETE RESTRICT,
            CONSTRAINT fk_lca_cost_layer FOREIGN KEY (cost_layer_id) REFERENCES cost_layer(id) ON DELETE RESTRICT,
            CONSTRAINT fk_lca_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT
        )');

        // Create indexes for landed_cost_allocation
        $this->addSql('CREATE INDEX idx_lca_landed_cost ON landed_cost_allocation (landed_cost_id)');
        $this->addSql('CREATE INDEX idx_lca_receipt_line ON landed_cost_allocation (receipt_line_id)');
        $this->addSql('CREATE INDEX idx_lca_cost_layer ON landed_cost_allocation (cost_layer_id)');
        $this->addSql('CREATE INDEX idx_lca_item ON landed_cost_allocation (item_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes
        $this->addSql('DROP INDEX IF EXISTS idx_lca_landed_cost');
        $this->addSql('DROP INDEX IF EXISTS idx_lca_receipt_line');
        $this->addSql('DROP INDEX IF EXISTS idx_lca_cost_layer');
        $this->addSql('DROP INDEX IF EXISTS idx_lca_item');

        // Drop landed_cost_allocation table
        $this->addSql('DROP TABLE landed_cost_allocation');

        // Drop indexes
        $this->addSql('DROP INDEX IF EXISTS idx_landed_cost_receipt');
        $this->addSql('DROP INDEX IF EXISTS idx_landed_cost_bill');
        $this->addSql('DROP INDEX IF EXISTS idx_landed_cost_date');

        // Drop landed_cost table
        $this->addSql('DROP TABLE landed_cost');
    }
}
