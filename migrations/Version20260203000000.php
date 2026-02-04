<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to restructure entities following NetSuite ERP transaction patterns.
 *
 * Changes:
 * 1. Add new fields to transaction tables from AbstractTransactionalEntity:
 *    - external_id, transaction_date, memo, posting_period
 *    - is_void, voided_at, voided_by, void_reason
 * 2. Add new fields to line tables from AbstractTransactionLineEntity:
 *    - line_number, line_memo, created_at, updated_at
 * 3. Migrate existing date fields to transaction_date
 * 4. Drop redundant columns that are now in abstract classes
 */
final class Version20260203000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restructure entities following NetSuite ERP transaction patterns with abstract base classes';
    }

    public function up(Schema $schema): void
    {
        // ============================================
        // TRANSACTION TABLES - Add new abstract fields
        // ============================================

        $transactionTables = [
            'purchase_order' => 'order_date',
            'sales_order' => 'order_date',
            'inventory_adjustment' => 'adjustment_date',
            'item_receipt' => 'receipt_date',
            'item_fulfillment' => 'fulfillment_date',
            'inventory_transfer' => 'transfer_date',
            'vendor_bill' => 'bill_date',
            'bill_payment' => 'payment_date',
            'physical_count' => 'count_date',
            'landed_cost' => 'applied_date',
        ];

        foreach ($transactionTables as $table => $oldDateColumn) {
            // Add new columns from AbstractTransactionalEntity
            $this->addSql("ALTER TABLE {$table} ADD external_id VARCHAR(100) DEFAULT NULL");
            $this->addSql("ALTER TABLE {$table} ADD is_void BOOLEAN NOT NULL DEFAULT FALSE");
            $this->addSql("ALTER TABLE {$table} ADD voided_at DATETIME DEFAULT NULL");
            $this->addSql("ALTER TABLE {$table} ADD voided_by VARCHAR(100) DEFAULT NULL");
            $this->addSql("ALTER TABLE {$table} ADD void_reason TEXT DEFAULT NULL");

            // Add transaction_date if not exists and migrate data
            if ($oldDateColumn !== 'transaction_date') {
                $this->addSql("ALTER TABLE {$table} ADD transaction_date DATETIME DEFAULT NULL");
                $this->addSql("UPDATE {$table} SET transaction_date = {$oldDateColumn}");
                $this->addSql("ALTER TABLE {$table} MODIFY transaction_date DATETIME NOT NULL");
            }

            // Add memo field if not exists (some tables already have it as notes/memo)
            if (!in_array($table, ['inventory_adjustment'])) {
                $this->addSql("ALTER TABLE {$table} ADD memo TEXT DEFAULT NULL");
            }

            // Add posting_period if not exists
            if (!in_array($table, ['inventory_adjustment'])) {
                $this->addSql("ALTER TABLE {$table} ADD posting_period VARCHAR(10) DEFAULT NULL");
            }
        }

        // ============================================
        // LINE TABLES - Add new abstract fields
        // ============================================

        $lineTables = [
            'purchase_order_line',
            'sales_order_line',
            'inventory_adjustment_line',
            'item_receipt_line',
            'item_fulfillment_line',
            'inventory_transfer_line',
            'vendor_bill_line',
            'physical_count_line',
            'landed_cost_allocation',
            'bill_payment_application',
        ];

        foreach ($lineTables as $table) {
            // Add line_number
            $this->addSql("ALTER TABLE {$table} ADD line_number INT NOT NULL DEFAULT 1");

            // Add line_memo
            $this->addSql("ALTER TABLE {$table} ADD line_memo TEXT DEFAULT NULL");

            // Add created_at and updated_at if not exists
            // inventory_transfer_line already has these
            if ($table !== 'inventory_transfer_line') {
                $this->addSql("ALTER TABLE {$table} ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
                $this->addSql("ALTER TABLE {$table} ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
            }
        }

        // ============================================
        // ITEM RECEIPT - Add receipt_number field
        // ============================================
        $this->addSql("ALTER TABLE item_receipt ADD receipt_number VARCHAR(55) DEFAULT NULL");
        $this->addSql("UPDATE item_receipt SET receipt_number = CONCAT('IR-', id)");
        $this->addSql("ALTER TABLE item_receipt MODIFY receipt_number VARCHAR(55) NOT NULL");
        $this->addSql("CREATE UNIQUE INDEX UNIQ_item_receipt_number ON item_receipt (receipt_number)");

        // ============================================
        // LANDED COST - Add status field
        // ============================================
        $this->addSql("ALTER TABLE landed_cost ADD status VARCHAR(50) NOT NULL DEFAULT 'draft'");
    }

    public function down(Schema $schema): void
    {
        // Remove new columns from transaction tables
        $transactionTables = [
            'purchase_order',
            'sales_order',
            'inventory_adjustment',
            'item_receipt',
            'item_fulfillment',
            'inventory_transfer',
            'vendor_bill',
            'bill_payment',
            'physical_count',
            'landed_cost',
        ];

        foreach ($transactionTables as $table) {
            $this->addSql("ALTER TABLE {$table} DROP COLUMN external_id");
            $this->addSql("ALTER TABLE {$table} DROP COLUMN is_void");
            $this->addSql("ALTER TABLE {$table} DROP COLUMN voided_at");
            $this->addSql("ALTER TABLE {$table} DROP COLUMN voided_by");
            $this->addSql("ALTER TABLE {$table} DROP COLUMN void_reason");
        }

        // Remove new columns from line tables
        $lineTables = [
            'purchase_order_line',
            'sales_order_line',
            'inventory_adjustment_line',
            'item_receipt_line',
            'item_fulfillment_line',
            'inventory_transfer_line',
            'vendor_bill_line',
            'physical_count_line',
            'landed_cost_allocation',
            'bill_payment_application',
        ];

        foreach ($lineTables as $table) {
            $this->addSql("ALTER TABLE {$table} DROP COLUMN line_number");
            $this->addSql("ALTER TABLE {$table} DROP COLUMN line_memo");
        }

        // Remove item receipt number
        $this->addSql("DROP INDEX UNIQ_item_receipt_number ON item_receipt");
        $this->addSql("ALTER TABLE item_receipt DROP COLUMN receipt_number");

        // Remove landed cost status
        $this->addSql("ALTER TABLE landed_cost DROP COLUMN status");
    }
}
