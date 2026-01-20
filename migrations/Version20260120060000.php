<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create Location table for multi-location warehouse management
 */
final class Version20260120060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Location table for NetSuite ERP multi-location warehouse management';
    }

    public function up(Schema $schema): void
    {
        // Create location table
        $this->addSql('CREATE TABLE location (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            location_code VARCHAR(50) UNIQUE NOT NULL,
            location_name VARCHAR(255) NOT NULL,
            location_type VARCHAR(50) NOT NULL DEFAULT \'warehouse\',
            active BOOLEAN NOT NULL DEFAULT TRUE,
            address JSON DEFAULT NULL,
            time_zone VARCHAR(100) DEFAULT NULL,
            country VARCHAR(2) DEFAULT NULL,
            use_bin_management BOOLEAN NOT NULL DEFAULT FALSE,
            requires_bin_on_receipt BOOLEAN NOT NULL DEFAULT FALSE,
            requires_bin_on_fulfillment BOOLEAN NOT NULL DEFAULT FALSE,
            default_bin_location VARCHAR(50) DEFAULT NULL,
            allow_negative_inventory BOOLEAN NOT NULL DEFAULT FALSE,
            is_transfer_source BOOLEAN NOT NULL DEFAULT TRUE,
            is_transfer_destination BOOLEAN NOT NULL DEFAULT TRUE,
            make_inventory_available BOOLEAN NOT NULL DEFAULT TRUE,
            manager_id INTEGER DEFAULT NULL,
            contact_phone VARCHAR(50) DEFAULT NULL,
            contact_email VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        // Create indexes for location
        $this->addSql('CREATE INDEX idx_location_code ON location (location_code)');
        $this->addSql('CREATE INDEX idx_location_type_active ON location (location_type, active)');

        // Insert default location for existing data
        $this->addSql("INSERT INTO location (
            uuid,
            location_code,
            location_name,
            location_type,
            active,
            use_bin_management,
            is_transfer_source,
            is_transfer_destination,
            make_inventory_available,
            created_at,
            updated_at
        ) VALUES (
            '01JHJMX0000000000000000000',
            'DEFAULT',
            'Default Location',
            'warehouse',
            TRUE,
            FALSE,
            TRUE,
            TRUE,
            TRUE,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )");
    }

    public function down(Schema $schema): void
    {
        // Drop indexes
        $this->addSql('DROP INDEX IF EXISTS idx_location_code');
        $this->addSql('DROP INDEX IF EXISTS idx_location_type_active');

        // Drop location table
        $this->addSql('DROP TABLE location');
    }
}
