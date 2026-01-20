<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 3: Bin Management - Create Bin and BinInventory tables
 */
final class Version20260120064000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 3: Create Bin and BinInventory tables for warehouse bin management';
    }

    public function up(Schema $schema): void
    {
        // Create bin table
        $this->addSql('CREATE TABLE bin (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            location_id INTEGER NOT NULL,
            bin_code VARCHAR(50) NOT NULL,
            bin_name VARCHAR(255),
            bin_type VARCHAR(50) NOT NULL DEFAULT \'storage\',
            zone VARCHAR(50),
            aisle VARCHAR(20),
            row VARCHAR(20),
            shelf VARCHAR(20),
            level VARCHAR(20),
            active BOOLEAN NOT NULL DEFAULT true,
            capacity DECIMAL(10, 2),
            current_utilization DECIMAL(10, 2) NOT NULL DEFAULT 0,
            allow_mixed_items BOOLEAN NOT NULL DEFAULT true,
            allow_mixed_lots BOOLEAN NOT NULL DEFAULT true,
            notes TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_bin_location FOREIGN KEY (location_id) REFERENCES location(id) ON DELETE RESTRICT
        )');

        // Add indexes for bin table
        $this->addSql('CREATE INDEX idx_bin_location_code ON bin(location_id, bin_code)');
        $this->addSql('CREATE INDEX idx_bin_location_active ON bin(location_id, active)');
        $this->addSql('CREATE INDEX idx_bin_type ON bin(bin_type)');
        $this->addSql('CREATE INDEX idx_bin_zone ON bin(location_id, zone)');

        // Create bin_inventory table
        $this->addSql('CREATE TABLE bin_inventory (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            item_id INTEGER NOT NULL,
            location_id INTEGER NOT NULL,
            bin_id INTEGER NOT NULL,
            lot_number VARCHAR(100),
            serial_numbers JSON,
            expiration_date DATE,
            quantity INTEGER NOT NULL DEFAULT 0,
            quality_status VARCHAR(50) NOT NULL DEFAULT \'available\',
            last_movement_date TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_bin_inv_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT,
            CONSTRAINT fk_bin_inv_location FOREIGN KEY (location_id) REFERENCES location(id) ON DELETE RESTRICT,
            CONSTRAINT fk_bin_inv_bin FOREIGN KEY (bin_id) REFERENCES bin(id) ON DELETE RESTRICT,
            CONSTRAINT uniq_bin_inv UNIQUE (item_id, location_id, bin_id, lot_number)
        )');

        // Add indexes for bin_inventory table
        $this->addSql('CREATE INDEX idx_bin_inv_item_location_bin ON bin_inventory(item_id, location_id, bin_id)');
        $this->addSql('CREATE INDEX idx_bin_inv_bin ON bin_inventory(bin_id)');
        $this->addSql('CREATE INDEX idx_bin_inv_quality ON bin_inventory(quality_status)');
        $this->addSql('CREATE INDEX idx_bin_inv_expiration ON bin_inventory(expiration_date) WHERE expiration_date IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Drop bin_inventory table
        $this->addSql('DROP TABLE IF EXISTS bin_inventory CASCADE');

        // Drop bin table
        $this->addSql('DROP TABLE IF EXISTS bin CASCADE');
    }
}
