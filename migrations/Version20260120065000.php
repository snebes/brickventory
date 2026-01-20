<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 4: Inter-Location Transfers - Create InventoryTransfer and InventoryTransferLine tables
 */
final class Version20260120065000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 4: Create InventoryTransfer and InventoryTransferLine tables for inter-location transfers';
    }

    public function up(Schema $schema): void
    {
        // Create inventory_transfer table
        $this->addSql('CREATE TABLE inventory_transfer (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            transfer_number VARCHAR(50) UNIQUE NOT NULL,
            from_location_id INTEGER NOT NULL,
            to_location_id INTEGER NOT NULL,
            transfer_date DATE NOT NULL,
            expected_delivery_date DATE,
            status VARCHAR(50) NOT NULL DEFAULT \'pending\',
            transfer_type VARCHAR(50) NOT NULL DEFAULT \'standard\',
            carrier VARCHAR(100),
            tracking_number VARCHAR(100),
            shipping_cost DECIMAL(10, 2),
            requested_by VARCHAR(255) NOT NULL,
            approved_by VARCHAR(255),
            approved_at TIMESTAMP,
            shipped_by VARCHAR(255),
            shipped_at TIMESTAMP,
            received_by VARCHAR(255),
            received_at TIMESTAMP,
            notes TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_transfer_from_location FOREIGN KEY (from_location_id) REFERENCES location(id) ON DELETE RESTRICT,
            CONSTRAINT fk_transfer_to_location FOREIGN KEY (to_location_id) REFERENCES location(id) ON DELETE RESTRICT
        )');

        // Add indexes for inventory_transfer table
        $this->addSql('CREATE INDEX idx_transfer_locations_status ON inventory_transfer(from_location_id, to_location_id, status)');
        $this->addSql('CREATE INDEX idx_transfer_status_date ON inventory_transfer(status, transfer_date)');
        $this->addSql('CREATE INDEX idx_transfer_from_location ON inventory_transfer(from_location_id)');
        $this->addSql('CREATE INDEX idx_transfer_to_location ON inventory_transfer(to_location_id)');
        $this->addSql('CREATE INDEX idx_transfer_status ON inventory_transfer(status)');

        // Create inventory_transfer_line table
        $this->addSql('CREATE TABLE inventory_transfer_line (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            inventory_transfer_id INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            from_bin_location VARCHAR(50),
            to_bin_location VARCHAR(50),
            quantity_requested INTEGER NOT NULL DEFAULT 0,
            quantity_shipped INTEGER NOT NULL DEFAULT 0,
            quantity_received INTEGER NOT NULL DEFAULT 0,
            lot_number VARCHAR(100),
            serial_numbers JSON,
            unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0,
            total_cost DECIMAL(10, 2) NOT NULL DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_transfer_line_transfer FOREIGN KEY (inventory_transfer_id) REFERENCES inventory_transfer(id) ON DELETE CASCADE,
            CONSTRAINT fk_transfer_line_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT
        )');

        // Add indexes for inventory_transfer_line table
        $this->addSql('CREATE INDEX idx_transfer_line_transfer ON inventory_transfer_line(inventory_transfer_id)');
        $this->addSql('CREATE INDEX idx_transfer_line_item ON inventory_transfer_line(item_id)');

        // Add transferReference column to cost_layer table
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN transfer_reference VARCHAR(50)');
        $this->addSql('CREATE INDEX idx_cost_layer_transfer_ref ON cost_layer(transfer_reference) WHERE transfer_reference IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Drop cost_layer transfer reference
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN IF EXISTS transfer_reference');

        // Drop inventory_transfer_line table
        $this->addSql('DROP TABLE IF EXISTS inventory_transfer_line CASCADE');

        // Drop inventory_transfer table
        $this->addSql('DROP TABLE IF EXISTS inventory_transfer CASCADE');
    }
}
