<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Expand CostLayer entity for NetSuite ERP workflow with landed costs and vendor tracking
 */
final class Version20260120053000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand CostLayer for NetSuite ERP workflow with original cost, landed cost adjustments, and vendor tracking';
    }

    public function up(Schema $schema): void
    {
        // Add new columns to cost_layer table
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN original_unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.0');
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN landed_cost_adjustments DECIMAL(10, 2) NOT NULL DEFAULT 0.0');
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN vendor_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN last_cost_adjustment TIMESTAMP DEFAULT NULL');

        // Set original_unit_cost to current unit_cost for existing records
        $this->addSql('UPDATE cost_layer SET original_unit_cost = unit_cost WHERE original_unit_cost = 0.0');

        // Add foreign key constraint for vendor
        $this->addSql('ALTER TABLE cost_layer ADD CONSTRAINT fk_cost_layer_vendor FOREIGN KEY (vendor_id) REFERENCES vendor(id) ON DELETE RESTRICT');

        // Create index for vendor
        $this->addSql('CREATE INDEX idx_cost_layer_vendor ON cost_layer (vendor_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop index
        $this->addSql('DROP INDEX IF EXISTS idx_cost_layer_vendor');

        // Drop foreign key
        $this->addSql('ALTER TABLE cost_layer DROP CONSTRAINT IF EXISTS fk_cost_layer_vendor');

        // Remove columns from cost_layer
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN original_unit_cost');
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN landed_cost_adjustments');
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN vendor_id');
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN last_cost_adjustment');
    }
}
