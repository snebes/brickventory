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
        $this->addSql('CREATE TABLE bill_payment (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, payment_number VARCHAR(55) NOT NULL, payment_date DATE NOT NULL, payment_method VARCHAR(50) NOT NULL, check_number VARCHAR(50) DEFAULT NULL, bank_account_id VARCHAR(50) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, exchange_rate NUMERIC(10, 6) DEFAULT NULL, total_amount NUMERIC(10, 2) NOT NULL, discount_taken NUMERIC(10, 2) NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, vendor_id INT NOT NULL, UNIQUE INDEX UNIQ_99EBB012D17F50A6 (uuid), UNIQUE INDEX UNIQ_99EBB012B3A884C2 (payment_number), INDEX idx_payment_vendor (vendor_id), INDEX idx_payment_status (status), INDEX idx_payment_date (payment_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE bill_payment_application (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, amount_applied NUMERIC(10, 2) NOT NULL, discount_applied NUMERIC(10, 2) NOT NULL, applied_at DATETIME NOT NULL, bill_payment_id INT NOT NULL, vendor_bill_id INT NOT NULL, UNIQUE INDEX UNIQ_EC7A603CD17F50A6 (uuid), INDEX idx_app_payment (bill_payment_id), INDEX idx_app_bill (vendor_bill_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE bin (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, bin_code VARCHAR(50) NOT NULL, bin_name VARCHAR(255) DEFAULT NULL, bin_type VARCHAR(50) NOT NULL, zone VARCHAR(50) DEFAULT NULL, aisle VARCHAR(20) DEFAULT NULL, row VARCHAR(20) DEFAULT NULL, shelf VARCHAR(20) DEFAULT NULL, level VARCHAR(20) DEFAULT NULL, active TINYINT NOT NULL, capacity NUMERIC(10, 2) DEFAULT NULL, current_utilization NUMERIC(10, 2) NOT NULL, allow_mixed_items TINYINT NOT NULL, allow_mixed_lots TINYINT NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, location_id INT NOT NULL, UNIQUE INDEX UNIQ_AA275AEDD17F50A6 (uuid), INDEX IDX_AA275AED64D218E (location_id), INDEX idx_bin_location_code (location_id, bin_code), INDEX idx_bin_location_active (location_id, active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE bin_inventory (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, lot_number VARCHAR(100) DEFAULT NULL, serial_numbers JSON DEFAULT NULL, expiration_date DATE DEFAULT NULL, quantity INT NOT NULL, quality_status VARCHAR(50) NOT NULL, last_movement_date DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, item_id INT NOT NULL, location_id INT NOT NULL, bin_id INT NOT NULL, UNIQUE INDEX UNIQ_C9B9416DD17F50A6 (uuid), INDEX IDX_C9B9416D126F525E (item_id), INDEX IDX_C9B9416D64D218E (location_id), INDEX idx_bin_inv_item_location_bin (item_id, location_id, bin_id), INDEX idx_bin_inv_bin (bin_id), UNIQUE INDEX uniq_bin_inv (item_id, location_id, bin_id, lot_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cost_layer (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, layer_type VARCHAR(50) NOT NULL, quality_status VARCHAR(50) NOT NULL, quantity_received INT NOT NULL, quantity_remaining INT NOT NULL, unit_cost NUMERIC(10, 2) NOT NULL, original_unit_cost NUMERIC(10, 2) NOT NULL, landed_cost_adjustments NUMERIC(10, 2) NOT NULL, receipt_date DATETIME NOT NULL, last_cost_adjustment DATETIME DEFAULT NULL, source_type VARCHAR(100) DEFAULT NULL, source_reference VARCHAR(100) DEFAULT NULL, voided TINYINT NOT NULL, void_reason LONGTEXT DEFAULT NULL, transfer_reference VARCHAR(50) DEFAULT NULL, item_id INT NOT NULL, item_receipt_line_id INT DEFAULT NULL, vendor_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_BF59971ED17F50A6 (uuid), INDEX IDX_BF59971E126F525E (item_id), INDEX IDX_BF59971EE4F00B1E (item_receipt_line_id), INDEX IDX_BF59971E126F525E3CE50CF6 (item_id, receipt_date), INDEX IDX_BF59971E126F525E58EB8C73C4C4AAC2 (item_id, layer_type, quality_status), INDEX idx_cost_layer_vendor (vendor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_adjustment (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, adjustment_number VARCHAR(50) NOT NULL, adjustment_date DATETIME NOT NULL, adjustment_type VARCHAR(50) NOT NULL, reason VARCHAR(50) NOT NULL, memo LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, posting_period VARCHAR(10) DEFAULT NULL, location_id INT DEFAULT NULL, total_quantity_change NUMERIC(10, 2) NOT NULL, total_value_change NUMERIC(10, 2) NOT NULL, approval_required TINYINT NOT NULL, approved_by VARCHAR(100) DEFAULT NULL, approved_at DATETIME DEFAULT NULL, posted_by VARCHAR(100) DEFAULT NULL, posted_at DATETIME DEFAULT NULL, reference_number VARCHAR(100) DEFAULT NULL, count_date DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_CA172D95D17F50A6 (uuid), UNIQUE INDEX UNIQ_CA172D9559A91512 (adjustment_number), INDEX IDX_CA172D957B00651CE6C4C66D (status, adjustment_date), INDEX IDX_CA172D95C084A63E7B00651C (adjustment_type, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_adjustment_line (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, adjustment_type VARCHAR(50) NOT NULL, quantity_change INT NOT NULL, quantity_before NUMERIC(10, 2) DEFAULT NULL, quantity_after NUMERIC(10, 2) DEFAULT NULL, current_unit_cost NUMERIC(10, 2) DEFAULT NULL, adjustment_unit_cost NUMERIC(10, 2) DEFAULT NULL, new_unit_cost NUMERIC(10, 2) DEFAULT NULL, total_cost_impact NUMERIC(10, 2) NOT NULL, bin_location VARCHAR(50) DEFAULT NULL, lot_number VARCHAR(100) DEFAULT NULL, serial_number VARCHAR(100) DEFAULT NULL, expense_account_id INT DEFAULT NULL, layers_affected JSON DEFAULT NULL, notes LONGTEXT DEFAULT NULL, inventory_adjustment_id INT NOT NULL, item_id INT NOT NULL, UNIQUE INDEX UNIQ_EFF3915FD17F50A6 (uuid), INDEX IDX_EFF3915F3E6060C9 (inventory_adjustment_id), INDEX IDX_EFF3915F126F525E (item_id), INDEX IDX_EFF3915F126F525E3E6060C9 (item_id, inventory_adjustment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_balance (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, bin_location VARCHAR(50) DEFAULT NULL, quantity_on_hand INT NOT NULL, quantity_available INT NOT NULL, quantity_committed INT NOT NULL, quantity_on_order INT NOT NULL, quantity_in_transit INT NOT NULL, quantity_reserved INT NOT NULL, quantity_backordered INT NOT NULL, average_cost NUMERIC(10, 2) NOT NULL, last_count_date DATE DEFAULT NULL, last_movement_date DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, item_id INT NOT NULL, location_id INT NOT NULL, UNIQUE INDEX UNIQ_EDF0B902D17F50A6 (uuid), INDEX IDX_EDF0B902126F525E (item_id), INDEX idx_inventory_balance_item_location (item_id, location_id), INDEX idx_inventory_balance_location (location_id), UNIQUE INDEX uniq_item_location_bin (item_id, location_id, bin_location), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_transfer (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, transfer_number VARCHAR(50) NOT NULL, transfer_date DATE NOT NULL, expected_delivery_date DATE DEFAULT NULL, status VARCHAR(50) NOT NULL, transfer_type VARCHAR(50) NOT NULL, carrier VARCHAR(100) DEFAULT NULL, tracking_number VARCHAR(100) DEFAULT NULL, shipping_cost NUMERIC(10, 2) DEFAULT NULL, requested_by VARCHAR(255) NOT NULL, approved_by VARCHAR(255) DEFAULT NULL, approved_at DATETIME DEFAULT NULL, shipped_by VARCHAR(255) DEFAULT NULL, shipped_at DATETIME DEFAULT NULL, received_by VARCHAR(255) DEFAULT NULL, received_at DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, from_location_id INT NOT NULL, to_location_id INT NOT NULL, UNIQUE INDEX UNIQ_F47E1951D17F50A6 (uuid), UNIQUE INDEX UNIQ_F47E1951F3834267 (transfer_number), INDEX IDX_F47E1951980210EB (from_location_id), INDEX IDX_F47E195128DE1FED (to_location_id), INDEX idx_transfer_locations_status (from_location_id, to_location_id, status), INDEX idx_transfer_status_date (status, transfer_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_transfer_line (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, from_bin_location VARCHAR(50) DEFAULT NULL, to_bin_location VARCHAR(50) DEFAULT NULL, quantity_requested INT NOT NULL, quantity_shipped INT NOT NULL, quantity_received INT NOT NULL, lot_number VARCHAR(100) DEFAULT NULL, serial_numbers JSON DEFAULT NULL, unit_cost NUMERIC(10, 2) NOT NULL, total_cost NUMERIC(10, 2) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, inventory_transfer_id INT NOT NULL, item_id INT NOT NULL, UNIQUE INDEX UNIQ_8AEC5B60D17F50A6 (uuid), INDEX IDX_8AEC5B6027827460 (inventory_transfer_id), INDEX IDX_8AEC5B60126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, item_id VARCHAR(55) NOT NULL, item_name LONGTEXT NOT NULL, quantity_available INT NOT NULL, quantity_on_hand INT NOT NULL, quantity_on_order INT NOT NULL, quantity_back_ordered INT NOT NULL, quantity_committed INT NOT NULL, element_ids VARCHAR(255) NOT NULL, part_id VARCHAR(255) NOT NULL, color_id VARCHAR(5) NOT NULL, category_id INT NOT NULL, UNIQUE INDEX UNIQ_1F1B251ED17F50A6 (uuid), UNIQUE INDEX UNIQ_1F1B251E126F525E (item_id), INDEX IDX_1F1B251E12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item_category (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, name LONGTEXT NOT NULL, UNIQUE INDEX UNIQ_6A41D10AD17F50A6 (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item_event (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, event_type VARCHAR(50) NOT NULL, quantity_change INT NOT NULL, event_date DATETIME NOT NULL, reference_type VARCHAR(100) DEFAULT NULL, reference_id INT DEFAULT NULL, metadata LONGTEXT DEFAULT NULL, item_id INT NOT NULL, UNIQUE INDEX UNIQ_11091177D17F50A6 (uuid), INDEX IDX_11091177126F525E (item_id), INDEX IDX_11091177126F525EB5557BD1 (item_id, event_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item_fulfillment (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, fulfillment_number VARCHAR(55) NOT NULL, fulfillment_date DATETIME NOT NULL, status VARCHAR(50) NOT NULL, ship_method VARCHAR(100) DEFAULT NULL, tracking_number VARCHAR(100) DEFAULT NULL, shipping_cost NUMERIC(10, 2) DEFAULT NULL, shipped_at DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, sales_order_id INT NOT NULL, fulfill_from_location_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_99A1A9B3D17F50A6 (uuid), UNIQUE INDEX UNIQ_99A1A9B399719282 (fulfillment_number), INDEX IDX_99A1A9B3C023F51C (sales_order_id), INDEX IDX_99A1A9B33B409FFD (fulfill_from_location_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item_fulfillment_line (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, quantity_fulfilled INT NOT NULL, serial_numbers JSON DEFAULT NULL, lot_number VARCHAR(100) DEFAULT NULL, bin_location VARCHAR(50) DEFAULT NULL, item_fulfillment_id INT NOT NULL, sales_order_line_id INT NOT NULL, item_id INT NOT NULL, UNIQUE INDEX UNIQ_930CE6A2D17F50A6 (uuid), INDEX IDX_930CE6A27F4D113E (item_fulfillment_id), INDEX IDX_930CE6A27E27AABA (sales_order_line_id), INDEX IDX_930CE6A2126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item_receipt (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, receipt_date DATETIME NOT NULL, notes LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, vendor_shipment_number VARCHAR(100) DEFAULT NULL, carrier VARCHAR(100) DEFAULT NULL, tracking_number VARCHAR(100) DEFAULT NULL, freight_cost NUMERIC(10, 2) NOT NULL, landed_cost_category VARCHAR(50) DEFAULT NULL, inspector_id INT DEFAULT NULL, inspection_notes LONGTEXT DEFAULT NULL, bill_immediately TINYINT NOT NULL, purchase_order_id INT NOT NULL, vendor_id INT DEFAULT NULL, received_at_location_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_58C49011D17F50A6 (uuid), INDEX IDX_58C49011A45D7E6A (purchase_order_id), INDEX IDX_58C490116C1B716 (received_at_location_id), INDEX idx_receipt_vendor (vendor_id), INDEX idx_receipt_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item_receipt_line (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, quantity_received INT NOT NULL, quantity_accepted INT NOT NULL, quantity_rejected INT NOT NULL, unit_cost NUMERIC(10, 2) NOT NULL, bin_location VARCHAR(50) DEFAULT NULL, lot_number VARCHAR(100) DEFAULT NULL, serial_numbers JSON DEFAULT NULL, expiration_date DATE DEFAULT NULL, receiving_notes LONGTEXT DEFAULT NULL, item_receipt_id INT NOT NULL, item_id INT NOT NULL, purchase_order_line_id INT NOT NULL, cost_layer_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_883572BBD17F50A6 (uuid), INDEX IDX_883572BB96ED809C (item_receipt_id), INDEX IDX_883572BB126F525E (item_id), INDEX IDX_883572BB6D136516 (purchase_order_line_id), INDEX IDX_883572BB5C4EBE78 (cost_layer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE landed_cost (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, landed_cost_number VARCHAR(55) NOT NULL, vendor_bill_id INT DEFAULT NULL, cost_category VARCHAR(50) NOT NULL, total_cost NUMERIC(10, 2) NOT NULL, allocation_method VARCHAR(50) NOT NULL, applied_date DATE NOT NULL, item_receipt_id INT NOT NULL, UNIQUE INDEX UNIQ_6A613DED17F50A6 (uuid), UNIQUE INDEX UNIQ_6A613DECB11C510 (landed_cost_number), INDEX idx_landed_cost_receipt (item_receipt_id), INDEX idx_landed_cost_bill (vendor_bill_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE landed_cost_allocation (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, allocated_amount NUMERIC(10, 2) NOT NULL, allocation_percentage NUMERIC(5, 4) NOT NULL, quantity NUMERIC(10, 2) NOT NULL, original_unit_cost NUMERIC(10, 2) NOT NULL, adjusted_unit_cost NUMERIC(10, 2) NOT NULL, landed_cost_id INT NOT NULL, receipt_line_id INT NOT NULL, cost_layer_id INT NOT NULL, item_id INT NOT NULL, UNIQUE INDEX UNIQ_FF6ED8E5D17F50A6 (uuid), INDEX IDX_FF6ED8E5126F525E (item_id), INDEX idx_lca_landed_cost (landed_cost_id), INDEX idx_lca_receipt_line (receipt_line_id), INDEX idx_lca_cost_layer (cost_layer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE layer_consumption (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, transaction_type VARCHAR(100) NOT NULL, transaction_id INT NOT NULL, quantity_consumed INT NOT NULL, unit_cost NUMERIC(10, 2) NOT NULL, total_cost NUMERIC(10, 2) NOT NULL, transaction_date DATETIME NOT NULL, cost_layer_id INT NOT NULL, reversal_of_id INT DEFAULT NULL, reversed_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_13546ED4D17F50A6 (uuid), INDEX IDX_13546ED45C4EBE78 (cost_layer_id), INDEX IDX_13546ED429A0BB4E (reversal_of_id), INDEX IDX_13546ED46EEF78AB (reversed_by_id), INDEX IDX_13546ED45C4EBE7848DD09DB (cost_layer_id, transaction_date), INDEX IDX_13546ED46E9D69882FC0CB0F (transaction_type, transaction_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE location (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, location_code VARCHAR(50) NOT NULL, location_name VARCHAR(255) NOT NULL, location_type VARCHAR(50) NOT NULL, active TINYINT NOT NULL, address JSON DEFAULT NULL, time_zone VARCHAR(100) DEFAULT NULL, country VARCHAR(2) DEFAULT NULL, use_bin_management TINYINT NOT NULL, requires_bin_on_receipt TINYINT NOT NULL, requires_bin_on_fulfillment TINYINT NOT NULL, default_bin_location VARCHAR(50) DEFAULT NULL, allow_negative_inventory TINYINT NOT NULL, is_transfer_source TINYINT NOT NULL, is_transfer_destination TINYINT NOT NULL, make_inventory_available TINYINT NOT NULL, manager_id INT DEFAULT NULL, contact_phone VARCHAR(50) DEFAULT NULL, contact_email VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5E9E89CBD17F50A6 (uuid), UNIQUE INDEX UNIQ_5E9E89CBF71185D8 (location_code), INDEX idx_location_code (location_code), INDEX idx_location_type_active (location_type, active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_event (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, order_type VARCHAR(50) NOT NULL, order_id INT NOT NULL, event_type VARCHAR(50) NOT NULL, event_date DATETIME NOT NULL, previous_state LONGTEXT DEFAULT NULL, new_state LONGTEXT DEFAULT NULL, metadata LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_B8307E5AD17F50A6 (uuid), INDEX IDX_B8307E5AC12F6D3E8D9F6D38B5557BD1 (order_type, order_id, event_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE physical_count (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, count_number VARCHAR(50) NOT NULL, count_type VARCHAR(50) NOT NULL, count_date DATETIME NOT NULL, location_id INT DEFAULT NULL, status VARCHAR(50) NOT NULL, scheduled_date DATETIME DEFAULT NULL, freeze_transactions TINYINT NOT NULL, completed_at DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_176F5114D17F50A6 (uuid), UNIQUE INDEX UNIQ_176F5114B921B640 (count_number), INDEX IDX_176F51147B00651CC493CE97 (status, count_date), INDEX IDX_176F511464D218E7B00651C (location_id, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE physical_count_line (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, location_id INT DEFAULT NULL, bin_location VARCHAR(50) DEFAULT NULL, lot_number VARCHAR(100) DEFAULT NULL, serial_number VARCHAR(100) DEFAULT NULL, system_quantity NUMERIC(10, 2) NOT NULL, counted_quantity NUMERIC(10, 2) DEFAULT NULL, variance_quantity NUMERIC(10, 2) NOT NULL, variance_percent NUMERIC(5, 2) NOT NULL, variance_value NUMERIC(10, 2) NOT NULL, counted_by VARCHAR(100) DEFAULT NULL, counted_at DATETIME DEFAULT NULL, verified_by VARCHAR(100) DEFAULT NULL, verified_at DATETIME DEFAULT NULL, recount_required TINYINT NOT NULL, recount_quantity NUMERIC(10, 2) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, physical_count_id INT NOT NULL, item_id INT NOT NULL, adjustment_line_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_2CE895E3D17F50A6 (uuid), INDEX IDX_2CE895E33C3DE57 (physical_count_id), INDEX IDX_2CE895E3126F525E (item_id), INDEX IDX_2CE895E3D20BA8ED (adjustment_line_id), INDEX IDX_2CE895E33C3DE57126F525E (physical_count_id, item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE purchase_order (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, order_number VARCHAR(55) NOT NULL, order_date DATETIME NOT NULL, status VARCHAR(50) NOT NULL, notes LONGTEXT DEFAULT NULL, reference VARCHAR(255) DEFAULT NULL, expected_receipt_date DATE DEFAULT NULL, bill_to_address JSON DEFAULT NULL, shipping_method VARCHAR(100) DEFAULT NULL, payment_terms VARCHAR(50) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, exchange_rate NUMERIC(10, 6) DEFAULT NULL, subtotal NUMERIC(10, 2) NOT NULL, tax_total NUMERIC(10, 2) NOT NULL, shipping_cost NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, buyer_id INT DEFAULT NULL, department_id INT DEFAULT NULL, approved_by INT DEFAULT NULL, approved_at DATETIME DEFAULT NULL, vendor_id INT DEFAULT NULL, ship_to_location_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_21E210B2D17F50A6 (uuid), UNIQUE INDEX UNIQ_21E210B2551F0F81 (order_number), INDEX IDX_21E210B21F4BA9E3 (ship_to_location_id), INDEX idx_po_vendor (vendor_id), INDEX idx_po_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE purchase_order_line (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, quantity_ordered INT NOT NULL, quantity_received INT NOT NULL, quantity_billed INT NOT NULL, rate NUMERIC(10, 2) NOT NULL, receiving_bin_location VARCHAR(50) DEFAULT NULL, tax_rate NUMERIC(5, 4) DEFAULT NULL, tax_amount NUMERIC(10, 2) DEFAULT NULL, expense_account_id INT DEFAULT NULL, expected_receipt_date DATE DEFAULT NULL, closed TINYINT NOT NULL, closed_reason VARCHAR(50) DEFAULT NULL, purchase_order_id INT NOT NULL, item_id INT NOT NULL, receiving_location_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_90D6D92BD17F50A6 (uuid), INDEX IDX_90D6D92BA45D7E6A (purchase_order_id), INDEX IDX_90D6D92B126F525E (item_id), INDEX IDX_90D6D92BFF632301 (receiving_location_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sales_order (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, order_number VARCHAR(55) NOT NULL, order_date DATETIME NOT NULL, status VARCHAR(50) NOT NULL, notes LONGTEXT DEFAULT NULL, fulfill_from_location_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_36D222ED17F50A6 (uuid), UNIQUE INDEX UNIQ_36D222E551F0F81 (order_number), INDEX IDX_36D222E3B409FFD (fulfill_from_location_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sales_order_line (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, quantity_ordered INT NOT NULL, quantity_committed INT NOT NULL, quantity_fulfilled INT NOT NULL, quantity_billed INT NOT NULL, pick_from_bin_location VARCHAR(50) DEFAULT NULL, sales_order_id INT NOT NULL, item_id INT NOT NULL, fulfill_from_location_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_93D9398DD17F50A6 (uuid), INDEX IDX_93D9398DC023F51C (sales_order_id), INDEX IDX_93D9398D126F525E (item_id), INDEX IDX_93D9398D3B409FFD (fulfill_from_location_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vendor (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, vendor_code VARCHAR(50) NOT NULL, vendor_name VARCHAR(255) NOT NULL, active TINYINT NOT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, billing_address JSON DEFAULT NULL, shipping_address JSON DEFAULT NULL, default_payment_terms VARCHAR(50) DEFAULT NULL, default_currency VARCHAR(3) DEFAULT NULL, credit_limit NUMERIC(12, 2) DEFAULT NULL, tax_id VARCHAR(50) DEFAULT NULL, tax_exempt TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_F52233F6D17F50A6 (uuid), UNIQUE INDEX UNIQ_F52233F65DD83547 (vendor_code), INDEX idx_vendor_code (vendor_code), INDEX idx_vendor_active (active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vendor_bill (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, bill_number VARCHAR(55) NOT NULL, vendor_invoice_number VARCHAR(100) DEFAULT NULL, vendor_invoice_date DATE DEFAULT NULL, bill_date DATE NOT NULL, due_date DATE NOT NULL, payment_terms VARCHAR(50) DEFAULT NULL, status VARCHAR(50) NOT NULL, currency VARCHAR(3) DEFAULT NULL, exchange_rate NUMERIC(10, 6) DEFAULT NULL, subtotal NUMERIC(10, 2) NOT NULL, tax_total NUMERIC(10, 2) NOT NULL, freight_amount NUMERIC(10, 2) NOT NULL, discount_amount NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, amount_paid NUMERIC(10, 2) NOT NULL, amount_due NUMERIC(10, 2) NOT NULL, approved_by INT DEFAULT NULL, approved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, vendor_id INT NOT NULL, purchase_order_id INT DEFAULT NULL, item_receipt_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_50EC1C3CD17F50A6 (uuid), UNIQUE INDEX UNIQ_50EC1C3C81115142 (bill_number), INDEX IDX_50EC1C3CA45D7E6A (purchase_order_id), INDEX IDX_50EC1C3C96ED809C (item_receipt_id), INDEX idx_bill_vendor (vendor_id), INDEX idx_bill_status (status), INDEX idx_bill_due_date (due_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vendor_bill_line (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, line_type VARCHAR(50) NOT NULL, description VARCHAR(255) NOT NULL, quantity NUMERIC(10, 2) NOT NULL, unit_cost NUMERIC(10, 2) NOT NULL, amount NUMERIC(10, 2) NOT NULL, expense_account_id INT DEFAULT NULL, variance_amount NUMERIC(10, 2) NOT NULL, variance_reason LONGTEXT DEFAULT NULL, vendor_bill_id INT NOT NULL, item_id INT DEFAULT NULL, receipt_line_id INT DEFAULT NULL, po_line_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_E86C0DDDD17F50A6 (uuid), INDEX IDX_E86C0DDD4FB2A9A (vendor_bill_id), INDEX IDX_E86C0DDD126F525E (item_id), INDEX IDX_E86C0DDD5E31D0BC (receipt_line_id), INDEX IDX_E86C0DDDCA08E8CD (po_line_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE bill_payment ADD CONSTRAINT FK_99EBB012F603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id)');
        $this->addSql('ALTER TABLE bill_payment_application ADD CONSTRAINT FK_EC7A603CAAFE27FF FOREIGN KEY (bill_payment_id) REFERENCES bill_payment (id)');
        $this->addSql('ALTER TABLE bill_payment_application ADD CONSTRAINT FK_EC7A603C4FB2A9A FOREIGN KEY (vendor_bill_id) REFERENCES vendor_bill (id)');
        $this->addSql('ALTER TABLE bin ADD CONSTRAINT FK_AA275AED64D218E FOREIGN KEY (location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE bin_inventory ADD CONSTRAINT FK_C9B9416D126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE bin_inventory ADD CONSTRAINT FK_C9B9416D64D218E FOREIGN KEY (location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE bin_inventory ADD CONSTRAINT FK_C9B9416D222586DC FOREIGN KEY (bin_id) REFERENCES bin (id)');
        $this->addSql('ALTER TABLE cost_layer ADD CONSTRAINT FK_BF59971E126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE cost_layer ADD CONSTRAINT FK_BF59971EE4F00B1E FOREIGN KEY (item_receipt_line_id) REFERENCES item_receipt_line (id)');
        $this->addSql('ALTER TABLE cost_layer ADD CONSTRAINT FK_BF59971EF603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id)');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD CONSTRAINT FK_EFF3915F3E6060C9 FOREIGN KEY (inventory_adjustment_id) REFERENCES inventory_adjustment (id)');
        $this->addSql('ALTER TABLE inventory_adjustment_line ADD CONSTRAINT FK_EFF3915F126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE inventory_balance ADD CONSTRAINT FK_EDF0B902126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE inventory_balance ADD CONSTRAINT FK_EDF0B90264D218E FOREIGN KEY (location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE inventory_transfer ADD CONSTRAINT FK_F47E1951980210EB FOREIGN KEY (from_location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE inventory_transfer ADD CONSTRAINT FK_F47E195128DE1FED FOREIGN KEY (to_location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE inventory_transfer_line ADD CONSTRAINT FK_8AEC5B6027827460 FOREIGN KEY (inventory_transfer_id) REFERENCES inventory_transfer (id)');
        $this->addSql('ALTER TABLE inventory_transfer_line ADD CONSTRAINT FK_8AEC5B60126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251E12469DE2 FOREIGN KEY (category_id) REFERENCES item_category (id)');
        $this->addSql('ALTER TABLE item_event ADD CONSTRAINT FK_11091177126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE item_fulfillment ADD CONSTRAINT FK_99A1A9B3C023F51C FOREIGN KEY (sales_order_id) REFERENCES sales_order (id)');
        $this->addSql('ALTER TABLE item_fulfillment ADD CONSTRAINT FK_99A1A9B33B409FFD FOREIGN KEY (fulfill_from_location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE item_fulfillment_line ADD CONSTRAINT FK_930CE6A27F4D113E FOREIGN KEY (item_fulfillment_id) REFERENCES item_fulfillment (id)');
        $this->addSql('ALTER TABLE item_fulfillment_line ADD CONSTRAINT FK_930CE6A27E27AABA FOREIGN KEY (sales_order_line_id) REFERENCES sales_order_line (id)');
        $this->addSql('ALTER TABLE item_fulfillment_line ADD CONSTRAINT FK_930CE6A2126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE item_receipt ADD CONSTRAINT FK_58C49011A45D7E6A FOREIGN KEY (purchase_order_id) REFERENCES purchase_order (id)');
        $this->addSql('ALTER TABLE item_receipt ADD CONSTRAINT FK_58C49011F603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id)');
        $this->addSql('ALTER TABLE item_receipt ADD CONSTRAINT FK_58C490116C1B716 FOREIGN KEY (received_at_location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE item_receipt_line ADD CONSTRAINT FK_883572BB96ED809C FOREIGN KEY (item_receipt_id) REFERENCES item_receipt (id)');
        $this->addSql('ALTER TABLE item_receipt_line ADD CONSTRAINT FK_883572BB126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE item_receipt_line ADD CONSTRAINT FK_883572BB6D136516 FOREIGN KEY (purchase_order_line_id) REFERENCES purchase_order_line (id)');
        $this->addSql('ALTER TABLE item_receipt_line ADD CONSTRAINT FK_883572BB5C4EBE78 FOREIGN KEY (cost_layer_id) REFERENCES cost_layer (id)');
        $this->addSql('ALTER TABLE landed_cost ADD CONSTRAINT FK_6A613DE96ED809C FOREIGN KEY (item_receipt_id) REFERENCES item_receipt (id)');
        $this->addSql('ALTER TABLE landed_cost_allocation ADD CONSTRAINT FK_FF6ED8E5BF0C9A43 FOREIGN KEY (landed_cost_id) REFERENCES landed_cost (id)');
        $this->addSql('ALTER TABLE landed_cost_allocation ADD CONSTRAINT FK_FF6ED8E55E31D0BC FOREIGN KEY (receipt_line_id) REFERENCES item_receipt_line (id)');
        $this->addSql('ALTER TABLE landed_cost_allocation ADD CONSTRAINT FK_FF6ED8E55C4EBE78 FOREIGN KEY (cost_layer_id) REFERENCES cost_layer (id)');
        $this->addSql('ALTER TABLE landed_cost_allocation ADD CONSTRAINT FK_FF6ED8E5126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE layer_consumption ADD CONSTRAINT FK_13546ED45C4EBE78 FOREIGN KEY (cost_layer_id) REFERENCES cost_layer (id)');
        $this->addSql('ALTER TABLE layer_consumption ADD CONSTRAINT FK_13546ED429A0BB4E FOREIGN KEY (reversal_of_id) REFERENCES layer_consumption (id)');
        $this->addSql('ALTER TABLE layer_consumption ADD CONSTRAINT FK_13546ED46EEF78AB FOREIGN KEY (reversed_by_id) REFERENCES layer_consumption (id)');
        $this->addSql('ALTER TABLE physical_count_line ADD CONSTRAINT FK_2CE895E33C3DE57 FOREIGN KEY (physical_count_id) REFERENCES physical_count (id)');
        $this->addSql('ALTER TABLE physical_count_line ADD CONSTRAINT FK_2CE895E3126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE physical_count_line ADD CONSTRAINT FK_2CE895E3D20BA8ED FOREIGN KEY (adjustment_line_id) REFERENCES inventory_adjustment_line (id)');
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT FK_21E210B2F603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id)');
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT FK_21E210B21F4BA9E3 FOREIGN KEY (ship_to_location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE purchase_order_line ADD CONSTRAINT FK_90D6D92BA45D7E6A FOREIGN KEY (purchase_order_id) REFERENCES purchase_order (id)');
        $this->addSql('ALTER TABLE purchase_order_line ADD CONSTRAINT FK_90D6D92B126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE purchase_order_line ADD CONSTRAINT FK_90D6D92BFF632301 FOREIGN KEY (receiving_location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE sales_order ADD CONSTRAINT FK_36D222E3B409FFD FOREIGN KEY (fulfill_from_location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE sales_order_line ADD CONSTRAINT FK_93D9398DC023F51C FOREIGN KEY (sales_order_id) REFERENCES sales_order (id)');
        $this->addSql('ALTER TABLE sales_order_line ADD CONSTRAINT FK_93D9398D126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE sales_order_line ADD CONSTRAINT FK_93D9398D3B409FFD FOREIGN KEY (fulfill_from_location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE vendor_bill ADD CONSTRAINT FK_50EC1C3CF603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id)');
        $this->addSql('ALTER TABLE vendor_bill ADD CONSTRAINT FK_50EC1C3CA45D7E6A FOREIGN KEY (purchase_order_id) REFERENCES purchase_order (id)');
        $this->addSql('ALTER TABLE vendor_bill ADD CONSTRAINT FK_50EC1C3C96ED809C FOREIGN KEY (item_receipt_id) REFERENCES item_receipt (id)');
        $this->addSql('ALTER TABLE vendor_bill_line ADD CONSTRAINT FK_E86C0DDD4FB2A9A FOREIGN KEY (vendor_bill_id) REFERENCES vendor_bill (id)');
        $this->addSql('ALTER TABLE vendor_bill_line ADD CONSTRAINT FK_E86C0DDD126F525E FOREIGN KEY (item_id) REFERENCES item (id)');
        $this->addSql('ALTER TABLE vendor_bill_line ADD CONSTRAINT FK_E86C0DDD5E31D0BC FOREIGN KEY (receipt_line_id) REFERENCES item_receipt_line (id)');
        $this->addSql('ALTER TABLE vendor_bill_line ADD CONSTRAINT FK_E86C0DDDCA08E8CD FOREIGN KEY (po_line_id) REFERENCES purchase_order_line (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order due to foreign key constraints
        $this->addSql('ALTER TABLE bill_payment DROP FOREIGN KEY FK_99EBB012F603EE73');
        $this->addSql('ALTER TABLE bill_payment_application DROP FOREIGN KEY FK_EC7A603CAAFE27FF');
        $this->addSql('ALTER TABLE bill_payment_application DROP FOREIGN KEY FK_EC7A603C4FB2A9A');
        $this->addSql('ALTER TABLE bin DROP FOREIGN KEY FK_AA275AED64D218E');
        $this->addSql('ALTER TABLE bin_inventory DROP FOREIGN KEY FK_C9B9416D126F525E');
        $this->addSql('ALTER TABLE bin_inventory DROP FOREIGN KEY FK_C9B9416D64D218E');
        $this->addSql('ALTER TABLE bin_inventory DROP FOREIGN KEY FK_C9B9416D222586DC');
        $this->addSql('ALTER TABLE cost_layer DROP FOREIGN KEY FK_BF59971E126F525E');
        $this->addSql('ALTER TABLE cost_layer DROP FOREIGN KEY FK_BF59971EE4F00B1E');
        $this->addSql('ALTER TABLE cost_layer DROP FOREIGN KEY FK_BF59971EF603EE73');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP FOREIGN KEY FK_EFF3915F3E6060C9');
        $this->addSql('ALTER TABLE inventory_adjustment_line DROP FOREIGN KEY FK_EFF3915F126F525E');
        $this->addSql('ALTER TABLE inventory_balance DROP FOREIGN KEY FK_EDF0B902126F525E');
        $this->addSql('ALTER TABLE inventory_balance DROP FOREIGN KEY FK_EDF0B90264D218E');
        $this->addSql('ALTER TABLE inventory_transfer DROP FOREIGN KEY FK_F47E1951980210EB');
        $this->addSql('ALTER TABLE inventory_transfer DROP FOREIGN KEY FK_F47E195128DE1FED');
        $this->addSql('ALTER TABLE inventory_transfer_line DROP FOREIGN KEY FK_8AEC5B6027827460');
        $this->addSql('ALTER TABLE inventory_transfer_line DROP FOREIGN KEY FK_8AEC5B60126F525E');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251E12469DE2');
        $this->addSql('ALTER TABLE item_event DROP FOREIGN KEY FK_11091177126F525E');
        $this->addSql('ALTER TABLE item_fulfillment DROP FOREIGN KEY FK_99A1A9B3C023F51C');
        $this->addSql('ALTER TABLE item_fulfillment DROP FOREIGN KEY FK_99A1A9B33B409FFD');
        $this->addSql('ALTER TABLE item_fulfillment_line DROP FOREIGN KEY FK_930CE6A27F4D113E');
        $this->addSql('ALTER TABLE item_fulfillment_line DROP FOREIGN KEY FK_930CE6A27E27AABA');
        $this->addSql('ALTER TABLE item_fulfillment_line DROP FOREIGN KEY FK_930CE6A2126F525E');
        $this->addSql('ALTER TABLE item_receipt DROP FOREIGN KEY FK_58C49011A45D7E6A');
        $this->addSql('ALTER TABLE item_receipt DROP FOREIGN KEY FK_58C49011F603EE73');
        $this->addSql('ALTER TABLE item_receipt DROP FOREIGN KEY FK_58C490116C1B716');
        $this->addSql('ALTER TABLE item_receipt_line DROP FOREIGN KEY FK_883572BB96ED809C');
        $this->addSql('ALTER TABLE item_receipt_line DROP FOREIGN KEY FK_883572BB126F525E');
        $this->addSql('ALTER TABLE item_receipt_line DROP FOREIGN KEY FK_883572BB6D136516');
        $this->addSql('ALTER TABLE item_receipt_line DROP FOREIGN KEY FK_883572BB5C4EBE78');
        $this->addSql('ALTER TABLE landed_cost DROP FOREIGN KEY FK_6A613DE96ED809C');
        $this->addSql('ALTER TABLE landed_cost_allocation DROP FOREIGN KEY FK_FF6ED8E5BF0C9A43');
        $this->addSql('ALTER TABLE landed_cost_allocation DROP FOREIGN KEY FK_FF6ED8E55E31D0BC');
        $this->addSql('ALTER TABLE landed_cost_allocation DROP FOREIGN KEY FK_FF6ED8E55C4EBE78');
        $this->addSql('ALTER TABLE landed_cost_allocation DROP FOREIGN KEY FK_FF6ED8E5126F525E');
        $this->addSql('ALTER TABLE layer_consumption DROP FOREIGN KEY FK_13546ED45C4EBE78');
        $this->addSql('ALTER TABLE layer_consumption DROP FOREIGN KEY FK_13546ED429A0BB4E');
        $this->addSql('ALTER TABLE layer_consumption DROP FOREIGN KEY FK_13546ED46EEF78AB');
        $this->addSql('ALTER TABLE physical_count_line DROP FOREIGN KEY FK_2CE895E33C3DE57');
        $this->addSql('ALTER TABLE physical_count_line DROP FOREIGN KEY FK_2CE895E3126F525E');
        $this->addSql('ALTER TABLE physical_count_line DROP FOREIGN KEY FK_2CE895E3D20BA8ED');
        $this->addSql('ALTER TABLE purchase_order DROP FOREIGN KEY FK_21E210B2F603EE73');
        $this->addSql('ALTER TABLE purchase_order DROP FOREIGN KEY FK_21E210B21F4BA9E3');
        $this->addSql('ALTER TABLE purchase_order_line DROP FOREIGN KEY FK_90D6D92BA45D7E6A');
        $this->addSql('ALTER TABLE purchase_order_line DROP FOREIGN KEY FK_90D6D92B126F525E');
        $this->addSql('ALTER TABLE purchase_order_line DROP FOREIGN KEY FK_90D6D92BFF632301');
        $this->addSql('ALTER TABLE sales_order DROP FOREIGN KEY FK_36D222E3B409FFD');
        $this->addSql('ALTER TABLE sales_order_line DROP FOREIGN KEY FK_93D9398DC023F51C');
        $this->addSql('ALTER TABLE sales_order_line DROP FOREIGN KEY FK_93D9398D126F525E');
        $this->addSql('ALTER TABLE sales_order_line DROP FOREIGN KEY FK_93D9398D3B409FFD');
        $this->addSql('ALTER TABLE vendor_bill DROP FOREIGN KEY FK_50EC1C3CF603EE73');
        $this->addSql('ALTER TABLE vendor_bill DROP FOREIGN KEY FK_50EC1C3CA45D7E6A');
        $this->addSql('ALTER TABLE vendor_bill DROP FOREIGN KEY FK_50EC1C3C96ED809C');
        $this->addSql('ALTER TABLE vendor_bill_line DROP FOREIGN KEY FK_E86C0DDD4FB2A9A');
        $this->addSql('ALTER TABLE vendor_bill_line DROP FOREIGN KEY FK_E86C0DDD126F525E');
        $this->addSql('ALTER TABLE vendor_bill_line DROP FOREIGN KEY FK_E86C0DDD5E31D0BC');
        $this->addSql('ALTER TABLE vendor_bill_line DROP FOREIGN KEY FK_E86C0DDDCA08E8CD');
        $this->addSql('DROP TABLE bill_payment');
        $this->addSql('DROP TABLE bill_payment_application');
        $this->addSql('DROP TABLE bin');
        $this->addSql('DROP TABLE bin_inventory');
        $this->addSql('DROP TABLE cost_layer');
        $this->addSql('DROP TABLE inventory_adjustment');
        $this->addSql('DROP TABLE inventory_adjustment_line');
        $this->addSql('DROP TABLE inventory_balance');
        $this->addSql('DROP TABLE inventory_transfer');
        $this->addSql('DROP TABLE inventory_transfer_line');
        $this->addSql('DROP TABLE item');
        $this->addSql('DROP TABLE item_category');
        $this->addSql('DROP TABLE item_event');
        $this->addSql('DROP TABLE item_fulfillment');
        $this->addSql('DROP TABLE item_fulfillment_line');
        $this->addSql('DROP TABLE item_receipt');
        $this->addSql('DROP TABLE item_receipt_line');
        $this->addSql('DROP TABLE landed_cost');
        $this->addSql('DROP TABLE landed_cost_allocation');
        $this->addSql('DROP TABLE layer_consumption');
        $this->addSql('DROP TABLE location');
        $this->addSql('DROP TABLE order_event');
        $this->addSql('DROP TABLE physical_count');
        $this->addSql('DROP TABLE physical_count_line');
        $this->addSql('DROP TABLE purchase_order');
        $this->addSql('DROP TABLE purchase_order_line');
        $this->addSql('DROP TABLE sales_order');
        $this->addSql('DROP TABLE sales_order_line');
        $this->addSql('DROP TABLE vendor');
        $this->addSql('DROP TABLE vendor_bill');
        $this->addSql('DROP TABLE vendor_bill_line');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
