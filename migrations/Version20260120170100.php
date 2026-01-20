<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add NetSuite ERP standard fields to entities
 * Adds createdBy and modifiedBy fields to all transactional and master data entities
 * Adds missing createdAt, updatedAt, and active fields to Item and ItemCategory entities
 */
final class Version20260120170100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add NetSuite ERP standard fields (createdBy, modifiedBy) and missing timestamp/active fields';
    }

    public function up(Schema $schema): void
    {
        // Add createdBy and modifiedBy to all transactional entities
        $transactionalTables = [
            'purchase_order',
            'sales_order',
            'item_receipt',
            'item_fulfillment',
            'inventory_adjustment',
            'inventory_transfer',
            'vendor_bill',
            'bill_payment',
            'landed_cost',
            'physical_count',
        ];

        foreach ($transactionalTables as $table) {
            $this->addSql("ALTER TABLE {$table} ADD created_by VARCHAR(100) DEFAULT NULL, ADD modified_by VARCHAR(100) DEFAULT NULL");
        }

        // Add createdBy, modifiedBy to master data entities
        $masterDataTables = [
            'location',
            'vendor',
            'bin',
        ];

        foreach ($masterDataTables as $table) {
            $this->addSql("ALTER TABLE {$table} ADD created_by VARCHAR(100) DEFAULT NULL, ADD modified_by VARCHAR(100) DEFAULT NULL");
        }

        // Add missing fields to Item entity (createdAt, updatedAt, active, createdBy, modifiedBy)
        $this->addSql('ALTER TABLE item ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ADD active TINYINT(1) NOT NULL DEFAULT 1, ADD created_by VARCHAR(100) DEFAULT NULL, ADD modified_by VARCHAR(100) DEFAULT NULL');

        // Add missing fields to ItemCategory entity (createdAt, updatedAt, active, createdBy, modifiedBy)
        $this->addSql('ALTER TABLE item_category ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ADD active TINYINT(1) NOT NULL DEFAULT 1, ADD created_by VARCHAR(100) DEFAULT NULL, ADD modified_by VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove createdBy and modifiedBy from all transactional entities
        $transactionalTables = [
            'purchase_order',
            'sales_order',
            'item_receipt',
            'item_fulfillment',
            'inventory_adjustment',
            'inventory_transfer',
            'vendor_bill',
            'bill_payment',
            'landed_cost',
            'physical_count',
        ];

        foreach ($transactionalTables as $table) {
            $this->addSql("ALTER TABLE {$table} DROP created_by, DROP modified_by");
        }

        // Remove createdBy, modifiedBy from master data entities
        $masterDataTables = [
            'location',
            'vendor',
            'bin',
        ];

        foreach ($masterDataTables as $table) {
            $this->addSql("ALTER TABLE {$table} DROP created_by, DROP modified_by");
        }

        // Remove fields from Item entity
        $this->addSql('ALTER TABLE item DROP created_at, DROP updated_at, DROP active, DROP created_by, DROP modified_by');

        // Remove fields from ItemCategory entity
        $this->addSql('ALTER TABLE item_category DROP created_at, DROP updated_at, DROP active, DROP created_by, DROP modified_by');
    }
}
