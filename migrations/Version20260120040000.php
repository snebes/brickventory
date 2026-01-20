<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NetSuite ERP Inventory Adjustment Workflow schema updates:
 * - Expand inventory_adjustment table with new fields
 * - Expand inventory_adjustment_line table with new fields
 * - Expand cost_layer table with tracking fields
 * - Create layer_consumption table for tracking
 * - Create physical_count and physical_count_line tables
 */
final class Version20260120040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'NetSuite ERP Inventory Adjustment Workflow schema updates';
    }

    public function up(Schema $schema): void
    {
        // Expand inventory_adjustment table
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN adjustment_type VARCHAR(50) DEFAULT \'quantity_adjustment\'');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN posting_period VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN location_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN total_quantity_change DECIMAL(10, 2) DEFAULT 0.00');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN total_value_change DECIMAL(10, 2) DEFAULT 0.00');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN approval_required BOOLEAN DEFAULT FALSE');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN approved_by VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN approved_at TIMESTAMP DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN posted_by VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN posted_at TIMESTAMP DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN reference_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment ADD COLUMN count_date TIMESTAMP DEFAULT NULL');
        
        // Update existing records with default adjustment_type
        $this->addSql('UPDATE inventory_adjustment SET adjustment_type = \'quantity_adjustment\' WHERE adjustment_type IS NULL');
        
        // Make adjustment_type NOT NULL after populating
        $this->addSql('ALTER TABLE inventory_adjustment ALTER COLUMN adjustment_type SET NOT NULL');
        
        // Update status field default value from 'approved' to 'draft'
        $this->addSql('ALTER TABLE inventory_adjustment ALTER COLUMN status SET DEFAULT \'draft\'');
        
        // Create indexes for inventory_adjustment
        $this->addSql('CREATE INDEX idx_inv_adj_status_date ON inventory_adjustment (status, adjustment_date)');
        $this->addSql('CREATE INDEX idx_inv_adj_type_status ON inventory_adjustment (adjustment_type, status)');

        // Expand inventory_adjustment_line table
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN adjustment_type VARCHAR(50) DEFAULT \'quantity\'');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN quantity_before DECIMAL(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN quantity_after DECIMAL(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN current_unit_cost DECIMAL(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN adjustment_unit_cost DECIMAL(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN new_unit_cost DECIMAL(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN total_cost_impact DECIMAL(10, 2) DEFAULT 0.00');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN bin_location VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN lot_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN serial_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN expense_account_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD COLUMN layers_affected JSON DEFAULT NULL');
        
        // Update existing records
        $this->addSql('UPDATE inventory_adjustment_line SET adjustment_type = \'quantity\' WHERE adjustment_type IS NULL');
        
        // Make adjustment_type NOT NULL
        $this->addSql('ALTER TABLE inventory_adjustment_line ALTER COLUMN adjustment_type SET NOT NULL');
        
        // Create index for inventory_adjustment_line
        $this->addSql('CREATE INDEX idx_inv_adj_line_item ON inventory_adjustment_line (item_id, inventory_adjustment_id)');

        // Expand cost_layer table
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN layer_type VARCHAR(50) DEFAULT \'receipt\'');
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN quality_status VARCHAR(50) DEFAULT \'available\'');
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN source_type VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN source_reference VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN voided BOOLEAN DEFAULT FALSE');
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN void_reason TEXT DEFAULT NULL');
        
        // Update existing records
        $this->addSql('UPDATE cost_layer SET layer_type = \'receipt\' WHERE layer_type IS NULL');
        $this->addSql('UPDATE cost_layer SET quality_status = \'available\' WHERE quality_status IS NULL');
        
        // Make columns NOT NULL
        $this->addSql('ALTER TABLE cost_layer ALTER COLUMN layer_type SET NOT NULL');
        $this->addSql('ALTER TABLE cost_layer ALTER COLUMN quality_status SET NOT NULL');
        
        // Create index for cost_layer
        $this->addSql('CREATE INDEX idx_cost_layer_type_quality ON cost_layer (item_id, layer_type, quality_status)');

        // Create layer_consumption table
        $this->addSql('CREATE TABLE layer_consumption (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            cost_layer_id INTEGER NOT NULL,
            transaction_type VARCHAR(100) NOT NULL,
            transaction_id INTEGER NOT NULL,
            quantity_consumed INTEGER NOT NULL,
            unit_cost DECIMAL(10, 2) NOT NULL,
            total_cost DECIMAL(10, 2) NOT NULL,
            transaction_date TIMESTAMP NOT NULL,
            reversal_of_id INTEGER DEFAULT NULL,
            reversed_by_id INTEGER DEFAULT NULL,
            CONSTRAINT fk_layer_consumption_layer FOREIGN KEY (cost_layer_id) REFERENCES cost_layer(id) ON DELETE RESTRICT,
            CONSTRAINT fk_layer_consumption_reversal_of FOREIGN KEY (reversal_of_id) REFERENCES layer_consumption(id) ON DELETE SET NULL,
            CONSTRAINT fk_layer_consumption_reversed_by FOREIGN KEY (reversed_by_id) REFERENCES layer_consumption(id) ON DELETE SET NULL
        )');
        
        // Create indexes for layer_consumption
        $this->addSql('CREATE INDEX idx_layer_consumption_layer ON layer_consumption (cost_layer_id, transaction_date)');
        $this->addSql('CREATE INDEX idx_layer_consumption_transaction ON layer_consumption (transaction_type, transaction_id)');

        // Create physical_count table
        $this->addSql('CREATE TABLE physical_count (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            count_number VARCHAR(50) UNIQUE NOT NULL,
            count_type VARCHAR(50) NOT NULL DEFAULT \'full_physical\',
            count_date TIMESTAMP NOT NULL,
            location_id INTEGER DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'planned\',
            scheduled_date TIMESTAMP DEFAULT NULL,
            freeze_transactions BOOLEAN DEFAULT FALSE,
            completed_at TIMESTAMP DEFAULT NULL,
            notes TEXT DEFAULT NULL
        )');
        
        // Create indexes for physical_count
        $this->addSql('CREATE INDEX idx_physical_count_status_date ON physical_count (status, count_date)');
        $this->addSql('CREATE INDEX idx_physical_count_location ON physical_count (location_id, status)');

        // Create physical_count_line table
        $this->addSql('CREATE TABLE physical_count_line (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            physical_count_id INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            location_id INTEGER DEFAULT NULL,
            bin_location VARCHAR(50) DEFAULT NULL,
            lot_number VARCHAR(100) DEFAULT NULL,
            serial_number VARCHAR(100) DEFAULT NULL,
            system_quantity DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            counted_quantity DECIMAL(10, 2) DEFAULT NULL,
            variance_quantity DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            variance_percent DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
            variance_value DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            counted_by VARCHAR(100) DEFAULT NULL,
            counted_at TIMESTAMP DEFAULT NULL,
            verified_by VARCHAR(100) DEFAULT NULL,
            verified_at TIMESTAMP DEFAULT NULL,
            recount_required BOOLEAN DEFAULT FALSE,
            recount_quantity DECIMAL(10, 2) DEFAULT NULL,
            adjustment_line_id INTEGER DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            CONSTRAINT fk_physical_count_line_count FOREIGN KEY (physical_count_id) REFERENCES physical_count(id) ON DELETE CASCADE,
            CONSTRAINT fk_physical_count_line_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT,
            CONSTRAINT fk_physical_count_line_adj_line FOREIGN KEY (adjustment_line_id) REFERENCES inventory_adjustment_line(id) ON DELETE SET NULL
        )');
        
        // Create indexes for physical_count_line
        $this->addSql('CREATE INDEX idx_physical_count_line_count_item ON physical_count_line (physical_count_id, item_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop physical_count_line table
        $this->addSql('DROP TABLE physical_count_line');
        
        // Drop physical_count table
        $this->addSql('DROP TABLE physical_count');
        
        // Drop layer_consumption table
        $this->addSql('DROP TABLE layer_consumption');
        
        // Remove cost_layer new columns
        $this->addSql('DROP INDEX IF EXISTS idx_cost_layer_type_quality');
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN void_reason');
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN voided');
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN source_reference');
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN source_type');
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN quality_status');
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN layer_type');
        
        // Remove inventory_adjustment_line new columns
        $this->addSql('DROP INDEX IF EXISTS idx_inv_adj_line_item');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN layers_affected');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN expense_account_id');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN serial_number');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN lot_number');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN bin_location');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN total_cost_impact');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN new_unit_cost');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN adjustment_unit_cost');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN current_unit_cost');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN quantity_after');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN quantity_before');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP COLUMN adjustment_type');
        
        // Remove inventory_adjustment new columns
        $this->addSql('DROP INDEX IF EXISTS idx_inv_adj_status_date');
        $this->addSql('DROP INDEX IF EXISTS idx_inv_adj_type_status');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN count_date');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN reference_number');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN posted_at');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN posted_by');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN approved_at');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN approved_by');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN approval_required');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN total_value_change');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN total_quantity_change');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN location_id');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN posting_period');
        $this->addSql('ALTER TABLE inventory_adjustment DROP COLUMN adjustment_type');
        $this->addSql('ALTER TABLE inventory_adjustment ALTER COLUMN status SET DEFAULT \'approved\'');
    }
}
