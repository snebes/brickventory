<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to integrate location management into Purchase Orders and Item Receipts
 * 
 * This migration:
 * 1. Renames ship_to_location_id to location_id in purchase_order (makes it NOT NULL)
 * 2. Renames received_at_location_id to location_id in item_receipt (makes it NOT NULL)
 * 3. Sets existing records to DEFAULT location
 * 4. Adds indexes for performance
 * 5. Adds foreign key constraints with ON DELETE RESTRICT
 */
final class Version20260120070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Integrate location management into Purchase Orders and Item Receipts';
    }

    public function up(Schema $schema): void
    {
        // Get the DEFAULT location ID
        $defaultLocationId = $this->connection->fetchOne(
            "SELECT id FROM location WHERE location_code = 'DEFAULT' LIMIT 1"
        );

        if (!$defaultLocationId) {
            throw new \RuntimeException('DEFAULT location not found. Please ensure the location exists before running this migration.');
        }

        // ===== PURCHASE ORDER UPDATES =====
        
        // Step 1: Update existing NULL values to DEFAULT location
        $this->addSql(
            'UPDATE purchase_order SET ship_to_location_id = :locationId WHERE ship_to_location_id IS NULL',
            ['locationId' => $defaultLocationId]
        );

        // Step 2: Drop old foreign key if it exists
        $this->addSql('ALTER TABLE purchase_order DROP FOREIGN KEY IF EXISTS FK_21E210B21F4BA9E3');
        
        // Step 3: Drop old index if it exists
        $this->addSql('ALTER TABLE purchase_order DROP INDEX IF EXISTS IDX_21E210B21F4BA9E3');

        // Step 4: Rename column from ship_to_location_id to location_id
        $this->addSql('ALTER TABLE purchase_order CHANGE ship_to_location_id location_id INT NOT NULL');

        // Step 5: Add new index
        $this->addSql('CREATE INDEX idx_po_location ON purchase_order (location_id)');

        // Step 6: Add foreign key constraint with ON DELETE RESTRICT
        $this->addSql(
            'ALTER TABLE purchase_order ADD CONSTRAINT FK_21E210B264D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE RESTRICT'
        );

        // ===== ITEM RECEIPT UPDATES =====
        
        // Step 1: Update existing NULL values to DEFAULT location
        $this->addSql(
            'UPDATE item_receipt SET received_at_location_id = :locationId WHERE received_at_location_id IS NULL',
            ['locationId' => $defaultLocationId]
        );

        // Step 2: Drop old foreign key if it exists
        $this->addSql('ALTER TABLE item_receipt DROP FOREIGN KEY IF EXISTS FK_58C490116C1B716');
        
        // Step 3: Drop old index if it exists
        $this->addSql('ALTER TABLE item_receipt DROP INDEX IF EXISTS IDX_58C490116C1B716');

        // Step 4: Rename column from received_at_location_id to location_id
        $this->addSql('ALTER TABLE item_receipt CHANGE received_at_location_id location_id INT NOT NULL');

        // Step 5: Add new index
        $this->addSql('CREATE INDEX idx_receipt_location ON item_receipt (location_id)');

        // Step 6: Add foreign key constraint with ON DELETE RESTRICT
        $this->addSql(
            'ALTER TABLE item_receipt ADD CONSTRAINT FK_58C4901164D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE RESTRICT'
        );
    }

    public function down(Schema $schema): void
    {
        // ===== ITEM RECEIPT ROLLBACK =====
        
        // Drop foreign key
        $this->addSql('ALTER TABLE item_receipt DROP FOREIGN KEY FK_58C4901164D218E');
        
        // Drop index
        $this->addSql('DROP INDEX idx_receipt_location ON item_receipt');
        
        // Rename column back and make nullable
        $this->addSql('ALTER TABLE item_receipt CHANGE location_id received_at_location_id INT DEFAULT NULL');
        
        // Add back old index
        $this->addSql('CREATE INDEX IDX_58C490116C1B716 ON item_receipt (received_at_location_id)');
        
        // Add back old foreign key
        $this->addSql(
            'ALTER TABLE item_receipt ADD CONSTRAINT FK_58C490116C1B716 FOREIGN KEY (received_at_location_id) REFERENCES location (id)'
        );

        // ===== PURCHASE ORDER ROLLBACK =====
        
        // Drop foreign key
        $this->addSql('ALTER TABLE purchase_order DROP FOREIGN KEY FK_21E210B264D218E');
        
        // Drop index
        $this->addSql('DROP INDEX idx_po_location ON purchase_order');
        
        // Rename column back and make nullable
        $this->addSql('ALTER TABLE purchase_order CHANGE location_id ship_to_location_id INT DEFAULT NULL');
        
        // Add back old index
        $this->addSql('CREATE INDEX IDX_21E210B21F4BA9E3 ON purchase_order (ship_to_location_id)');
        
        // Add back old foreign key
        $this->addSql(
            'ALTER TABLE purchase_order ADD CONSTRAINT FK_21E210B21F4BA9E3 FOREIGN KEY (ship_to_location_id) REFERENCES location (id)'
        );
    }
}
