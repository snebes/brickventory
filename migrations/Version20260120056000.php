<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create BillPayment and BillPaymentApplication tables for NetSuite ERP workflow
 */
final class Version20260120056000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create BillPayment and BillPaymentApplication tables for vendor payment management';
    }

    public function up(Schema $schema): void
    {
        // Create bill_payment table
        $this->addSql('CREATE TABLE bill_payment (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            payment_number VARCHAR(55) UNIQUE NOT NULL,
            vendor_id INTEGER NOT NULL,
            payment_date DATE NOT NULL,
            payment_method VARCHAR(50) NOT NULL DEFAULT \'Check\',
            check_number VARCHAR(50) DEFAULT NULL,
            bank_account_id VARCHAR(50) DEFAULT NULL,
            currency VARCHAR(3) DEFAULT NULL,
            exchange_rate DECIMAL(10, 6) DEFAULT NULL,
            total_amount DECIMAL(10, 2) NOT NULL,
            discount_taken DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            status VARCHAR(50) NOT NULL DEFAULT \'Draft\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_payment_vendor FOREIGN KEY (vendor_id) REFERENCES vendor(id) ON DELETE RESTRICT
        )');

        // Create indexes for bill_payment
        $this->addSql('CREATE INDEX idx_payment_vendor ON bill_payment (vendor_id)');
        $this->addSql('CREATE INDEX idx_payment_status ON bill_payment (status)');
        $this->addSql('CREATE INDEX idx_payment_date ON bill_payment (payment_date)');
        $this->addSql('CREATE INDEX idx_payment_method ON bill_payment (payment_method)');

        // Create bill_payment_application table
        $this->addSql('CREATE TABLE bill_payment_application (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            bill_payment_id INTEGER NOT NULL,
            vendor_bill_id INTEGER NOT NULL,
            amount_applied DECIMAL(10, 2) NOT NULL,
            discount_applied DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_app_payment FOREIGN KEY (bill_payment_id) REFERENCES bill_payment(id) ON DELETE CASCADE,
            CONSTRAINT fk_app_bill FOREIGN KEY (vendor_bill_id) REFERENCES vendor_bill(id) ON DELETE RESTRICT
        )');

        // Create indexes for bill_payment_application
        $this->addSql('CREATE INDEX idx_app_payment ON bill_payment_application (bill_payment_id)');
        $this->addSql('CREATE INDEX idx_app_bill ON bill_payment_application (vendor_bill_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes
        $this->addSql('DROP INDEX IF EXISTS idx_app_payment');
        $this->addSql('DROP INDEX IF EXISTS idx_app_bill');

        // Drop bill_payment_application table
        $this->addSql('DROP TABLE bill_payment_application');

        // Drop indexes
        $this->addSql('DROP INDEX IF EXISTS idx_payment_vendor');
        $this->addSql('DROP INDEX IF EXISTS idx_payment_status');
        $this->addSql('DROP INDEX IF EXISTS idx_payment_date');
        $this->addSql('DROP INDEX IF EXISTS idx_payment_method');

        // Drop bill_payment table
        $this->addSql('DROP TABLE bill_payment');
    }
}
