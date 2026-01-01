<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add item_event table for event sourcing pattern
 */
final class Version20260101223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create item_event table for event sourcing pattern';
    }

    public function up(Schema $schema): void
    {
        // Create item_event table
        $this->addSql('CREATE TABLE item_event (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            item_id INTEGER NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            quantity_change INTEGER NOT NULL,
            event_date TIMESTAMP NOT NULL,
            reference_type VARCHAR(100) DEFAULT NULL,
            reference_id INTEGER DEFAULT NULL,
            metadata TEXT DEFAULT NULL,
            CONSTRAINT fk_item_event_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT
        )');
        
        // Create index for efficient querying
        $this->addSql('CREATE INDEX idx_item_event_item_date ON item_event (item_id, event_date)');
    }

    public function down(Schema $schema): void
    {
        // Drop the item_event table
        $this->addSql('DROP TABLE item_event');
    }
}
