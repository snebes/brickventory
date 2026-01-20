<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create InventoryBalance table for location-specific inventory tracking
 */
final class Version20260120061000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create InventoryBalance table for location-specific inventory tracking';
    }

    public function up(Schema $schema): void
    {
        // Create inventory_balance table
        $this->addSql('CREATE TABLE inventory_balance (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            item_id INTEGER NOT NULL,
            location_id INTEGER NOT NULL,
            bin_location VARCHAR(50) DEFAULT NULL,
            quantity_on_hand INTEGER NOT NULL DEFAULT 0,
            quantity_available INTEGER NOT NULL DEFAULT 0,
            quantity_committed INTEGER NOT NULL DEFAULT 0,
            quantity_on_order INTEGER NOT NULL DEFAULT 0,
            quantity_in_transit INTEGER NOT NULL DEFAULT 0,
            quantity_reserved INTEGER NOT NULL DEFAULT 0,
            quantity_backordered INTEGER NOT NULL DEFAULT 0,
            average_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            last_count_date DATE DEFAULT NULL,
            last_movement_date TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_inventory_balance_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT,
            CONSTRAINT fk_inventory_balance_location FOREIGN KEY (location_id) REFERENCES location(id) ON DELETE RESTRICT,
            CONSTRAINT uniq_item_location_bin UNIQUE (item_id, location_id, bin_location)
        )');

        // Create indexes for inventory_balance
        $this->addSql('CREATE INDEX idx_inventory_balance_item_location ON inventory_balance (item_id, location_id)');
        $this->addSql('CREATE INDEX idx_inventory_balance_location ON inventory_balance (location_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes
        $this->addSql('DROP INDEX IF EXISTS idx_inventory_balance_item_location');
        $this->addSql('DROP INDEX IF EXISTS idx_inventory_balance_location');

        // Drop inventory_balance table
        $this->addSql('DROP TABLE inventory_balance');
    }
}
