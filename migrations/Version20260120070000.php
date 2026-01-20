<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make vendor_id required (NOT NULL) on purchase_order table
 * Following NetSuite ERP model where every Purchase Order must have a Vendor
 */
final class Version20260120070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make vendor_id required on purchase_order table - NetSuite ERP vendor requirement';
    }

    public function up(Schema $schema): void
    {
        // First, handle any existing POs without vendor - create placeholder vendor if needed
        $this->addSql("
            INSERT INTO vendor (uuid, vendor_code, vendor_name, active, created_at, updated_at)
            SELECT 
                gen_random_uuid()::text,
                'UNKNOWN',
                'Unknown Vendor - To Be Assigned',
                false,
                NOW(),
                NOW()
            WHERE NOT EXISTS (SELECT 1 FROM vendor WHERE vendor_code = 'UNKNOWN')
        ");

        // Update any POs without vendor_id to use the placeholder vendor
        $this->addSql("
            UPDATE purchase_order
            SET vendor_id = (SELECT id FROM vendor WHERE vendor_code = 'UNKNOWN' LIMIT 1)
            WHERE vendor_id IS NULL
        ");

        // Now make vendor_id NOT NULL
        $this->addSql('ALTER TABLE purchase_order ALTER COLUMN vendor_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Make vendor_id nullable again
        $this->addSql('ALTER TABLE purchase_order ALTER COLUMN vendor_id DROP NOT NULL');
    }
}
