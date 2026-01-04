<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add item_receipt_line table for tracking individual lines in item receipts
 */
final class Version20260104173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create item_receipt_line table for tracking received items per receipt line';
    }

    public function up(Schema $schema): void
    {
        // Note: This migration uses PostgreSQL-specific syntax (SERIAL, etc.)
        // The project is configured for PostgreSQL. For other databases, 
        // adjust the SQL accordingly or use Doctrine schema management.
        
        // Create item_receipt_line table
        $this->addSql('CREATE TABLE item_receipt_line (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            item_receipt_id INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            purchase_order_line_id INTEGER NOT NULL,
            quantity_received INTEGER NOT NULL CHECK (quantity_received > 0),
            CONSTRAINT fk_item_receipt_line_receipt FOREIGN KEY (item_receipt_id) REFERENCES item_receipt(id) ON DELETE CASCADE,
            CONSTRAINT fk_item_receipt_line_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT,
            CONSTRAINT fk_item_receipt_line_po_line FOREIGN KEY (purchase_order_line_id) REFERENCES purchase_order_line(id) ON DELETE RESTRICT
        )');
        
        // Create indexes for efficient querying
        $this->addSql('CREATE INDEX idx_item_receipt_line_receipt ON item_receipt_line (item_receipt_id)');
        $this->addSql('CREATE INDEX idx_item_receipt_line_item ON item_receipt_line (item_id)');
        $this->addSql('CREATE INDEX idx_item_receipt_line_po_line ON item_receipt_line (purchase_order_line_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop the item_receipt_line table
        $this->addSql('DROP TABLE item_receipt_line');
    }
}
