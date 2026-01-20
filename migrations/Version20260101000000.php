<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial database schema - creates base tables for the inventory management system
 */
final class Version20260101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial database schema with item, purchase_order, purchase_order_line, sales_order, sales_order_line, and item_receipt tables';
    }

    public function up(Schema $schema): void
    {
        // Create item table
        $this->addSql('CREATE TABLE item (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            item_number VARCHAR(50) UNIQUE NOT NULL,
            description VARCHAR(255) NOT NULL,
            quantity_on_hand INTEGER NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        // Create purchase_order table
        $this->addSql('CREATE TABLE purchase_order (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            po_number VARCHAR(50) UNIQUE NOT NULL,
            order_date TIMESTAMP NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'pending\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        // Create purchase_order_line table
        $this->addSql('CREATE TABLE purchase_order_line (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            purchase_order_id INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            quantity_ordered INTEGER NOT NULL,
            quantity_received INTEGER NOT NULL DEFAULT 0,
            CONSTRAINT fk_po_line_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_order(id) ON DELETE CASCADE,
            CONSTRAINT fk_po_line_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT
        )');

        // Create indexes for purchase_order_line
        $this->addSql('CREATE INDEX idx_po_line_po ON purchase_order_line (purchase_order_id)');
        $this->addSql('CREATE INDEX idx_po_line_item ON purchase_order_line (item_id)');

        // Create sales_order table
        $this->addSql('CREATE TABLE sales_order (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            so_number VARCHAR(50) UNIQUE NOT NULL,
            order_date TIMESTAMP NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'pending\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        // Create sales_order_line table
        $this->addSql('CREATE TABLE sales_order_line (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            sales_order_id INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            quantity_ordered INTEGER NOT NULL,
            quantity_fulfilled INTEGER NOT NULL DEFAULT 0,
            CONSTRAINT fk_so_line_so FOREIGN KEY (sales_order_id) REFERENCES sales_order(id) ON DELETE CASCADE,
            CONSTRAINT fk_so_line_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT
        )');

        // Create indexes for sales_order_line
        $this->addSql('CREATE INDEX idx_so_line_so ON sales_order_line (sales_order_id)');
        $this->addSql('CREATE INDEX idx_so_line_item ON sales_order_line (item_id)');

        // Create item_receipt table
        $this->addSql('CREATE TABLE item_receipt (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            receipt_number VARCHAR(50) UNIQUE NOT NULL,
            purchase_order_id INTEGER NOT NULL,
            receipt_date TIMESTAMP NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'received\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_receipt_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_order(id) ON DELETE RESTRICT
        )');

        // Create index for item_receipt
        $this->addSql('CREATE INDEX idx_receipt_po ON item_receipt (purchase_order_id)');

        // Create order_event table
        $this->addSql('CREATE TABLE order_event (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            order_type VARCHAR(50) NOT NULL,
            order_id INTEGER NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_date TIMESTAMP NOT NULL,
            metadata TEXT DEFAULT NULL
        )');

        // Create index for order_event
        $this->addSql('CREATE INDEX idx_order_event_order ON order_event (order_type, order_id, event_date)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order due to foreign key constraints
        $this->addSql('DROP TABLE IF EXISTS order_event');
        $this->addSql('DROP TABLE IF EXISTS item_receipt');
        $this->addSql('DROP TABLE IF EXISTS sales_order_line');
        $this->addSql('DROP TABLE IF EXISTS sales_order');
        $this->addSql('DROP TABLE IF EXISTS purchase_order_line');
        $this->addSql('DROP TABLE IF EXISTS purchase_order');
        $this->addSql('DROP TABLE IF EXISTS item');
    }
}
