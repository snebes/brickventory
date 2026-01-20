<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create vendor table for NetSuite-style procure-to-pay workflow
 */
final class Version20260120050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create vendor table for NetSuite ERP procure-to-pay workflow';
    }

    public function up(Schema $schema): void
    {
        // Create vendor table
        $this->addSql('CREATE TABLE vendor (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            vendor_code VARCHAR(50) UNIQUE NOT NULL,
            vendor_name VARCHAR(255) NOT NULL,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            billing_address JSON DEFAULT NULL,
            shipping_address JSON DEFAULT NULL,
            default_payment_terms VARCHAR(50) DEFAULT NULL,
            default_currency VARCHAR(3) DEFAULT NULL,
            credit_limit DECIMAL(12, 2) DEFAULT NULL,
            tax_id VARCHAR(50) DEFAULT NULL,
            tax_exempt BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        // Create indexes for efficient querying
        $this->addSql('CREATE INDEX idx_vendor_code ON vendor (vendor_code)');
        $this->addSql('CREATE INDEX idx_vendor_active ON vendor (active)');
        $this->addSql('CREATE INDEX idx_vendor_name ON vendor (vendor_name)');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes
        $this->addSql('DROP INDEX IF EXISTS idx_vendor_code');
        $this->addSql('DROP INDEX IF EXISTS idx_vendor_active');
        $this->addSql('DROP INDEX IF EXISTS idx_vendor_name');
        
        // Drop vendor table
        $this->addSql('DROP TABLE vendor');
    }
}
