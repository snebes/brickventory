<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to update inventory_adjustment table to use proper Location entity relationship.
 * This matches NetSuite ERP behavior where location is required on the header record.
 */
final class Version20260120165300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make location required on inventory_adjustment table (NetSuite ERP pattern)';
    }

    public function up(Schema $schema): void
    {
        // For existing records without a location, we need to set a default
        // This requires a location to exist in the database first
        // If no default location exists, this will fail and require manual intervention
        $this->addSql('
            UPDATE inventory_adjustment ia
            SET location_id = (SELECT MIN(id) FROM location WHERE active = 1)
            WHERE location_id IS NULL
        ');
        
        // Now make the column NOT NULL and add the foreign key
        $this->addSql('ALTER TABLE inventory_adjustment MODIFY location_id INT NOT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment ADD CONSTRAINT FK_F7CEFC7164D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_F7CEFC7164D218E ON inventory_adjustment (location_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove the foreign key and make the column nullable again
        $this->addSql('ALTER TABLE inventory_adjustment DROP FOREIGN KEY FK_F7CEFC7164D218E');
        $this->addSql('DROP INDEX IDX_F7CEFC7164D218E ON inventory_adjustment');
        $this->addSql('ALTER TABLE inventory_adjustment MODIFY location_id INT DEFAULT NULL');
    }
}
