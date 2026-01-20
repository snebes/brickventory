<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add location foreign keys to existing tables and migrate item quantities to InventoryBalance
 */
final class Version20260120062000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add location FKs to existing tables and migrate item quantities to InventoryBalance';
    }

    public function up(Schema $schema): void
    {
        // Update cost_layer to add location_id and bin_location if not exists
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN IF NOT EXISTS location_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE cost_layer ADD COLUMN IF NOT EXISTS bin_location VARCHAR(50) DEFAULT NULL');
        
        // Set default location for existing cost layers
        $this->addSql('UPDATE cost_layer SET location_id = (SELECT id FROM location WHERE location_code = \'DEFAULT\') WHERE location_id IS NULL');
        
        // Add foreign key constraint for cost_layer
        $this->addSql('ALTER TABLE cost_layer ADD CONSTRAINT fk_cost_layer_location FOREIGN KEY (location_id) REFERENCES location(id) ON DELETE RESTRICT');
        
        // Create index for cost_layer location queries
        $this->addSql('CREATE INDEX idx_cost_layer_location ON cost_layer (item_id, location_id, receipt_date)');

        // Migrate existing item quantities to inventory_balance
        // This creates balance records for items with non-zero quantities at the default location
        $this->addSql("INSERT INTO inventory_balance (
            uuid,
            item_id,
            location_id,
            bin_location,
            quantity_on_hand,
            quantity_available,
            quantity_committed,
            quantity_on_order,
            quantity_in_transit,
            quantity_reserved,
            quantity_backordered,
            average_cost,
            created_at,
            updated_at
        )
        SELECT 
            gen_random_uuid()::text,
            i.id,
            (SELECT id FROM location WHERE location_code = 'DEFAULT'),
            NULL,
            i.quantity_on_hand,
            i.quantity_available,
            i.quantity_committed,
            i.quantity_on_order,
            0,
            0,
            i.quantity_back_ordered,
            0.00,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        FROM item i
        WHERE i.quantity_on_hand != 0 
           OR i.quantity_available != 0 
           OR i.quantity_committed != 0 
           OR i.quantity_on_order != 0 
           OR i.quantity_back_ordered != 0
        ON CONFLICT (item_id, location_id, bin_location) DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        // Remove index
        $this->addSql('DROP INDEX IF EXISTS idx_cost_layer_location');
        
        // Remove foreign key constraint
        $this->addSql('ALTER TABLE cost_layer DROP CONSTRAINT IF EXISTS fk_cost_layer_location');
        
        // Remove location columns from cost_layer
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN IF EXISTS bin_location');
        $this->addSql('ALTER TABLE cost_layer DROP COLUMN IF EXISTS location_id');
        
        // Note: We don't migrate data back to item table as it would cause data loss
        // The inventory_balance table will be dropped in the previous migration's down()
    }
}
