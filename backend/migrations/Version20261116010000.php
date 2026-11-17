<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend inventory with variants, categories, warehouses, traceability and movement metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'This migration targets MySQL/MariaDB.');
        $this->addSql('CREATE TABLE inv_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, details JSON NOT NULL, created_at DATETIME NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_F95E9018727ACA70 (parent_id), PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE inv_trace_balance (id INT AUTO_INCREMENT NOT NULL, batch VARCHAR(100) NOT NULL, serial VARCHAR(100) NOT NULL, quantity INT NOT NULL, expires_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, product_id INT NOT NULL, location_id INT NOT NULL, UNIQUE INDEX inv_trace_target (product_id, location_id, batch, serial), INDEX IDX_259A32844584665A (product_id), INDEX IDX_259A328464D218E (location_id), PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE inv_warehouse (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, details JSON NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE inv_category ADD CONSTRAINT FK_F95E9018727ACA70 FOREIGN KEY (parent_id) REFERENCES inv_category (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inv_trace_balance ADD CONSTRAINT FK_259A32844584665A FOREIGN KEY (product_id) REFERENCES inv_product (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inv_trace_balance ADD CONSTRAINT FK_259A328464D218E FOREIGN KEY (location_id) REFERENCES inv_location (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inv_product ADD barcode VARCHAR(255) DEFAULT NULL, ADD min_stock INT DEFAULT 0 NOT NULL, ADD details JSON DEFAULT NULL, ADD parent_id INT DEFAULT NULL, ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inv_product ADD CONSTRAINT FK_CEA9AD74727ACA70 FOREIGN KEY (parent_id) REFERENCES inv_product (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inv_product ADD CONSTRAINT FK_CEA9AD7412469DE2 FOREIGN KEY (category_id) REFERENCES inv_category (id) ON DELETE RESTRICT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CEA9AD7497AE0266 ON inv_product (barcode)');
        $this->addSql('CREATE INDEX IDX_CEA9AD74727ACA70 ON inv_product (parent_id)');
        $this->addSql('CREATE INDEX IDX_CEA9AD7412469DE2 ON inv_product (category_id)');
        $this->addSql('ALTER TABLE inv_movement ADD details JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE inv_location ADD details JSON DEFAULT NULL, ADD warehouse_id INT DEFAULT NULL, ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inv_location ADD CONSTRAINT FK_A18C00125080ECDE FOREIGN KEY (warehouse_id) REFERENCES inv_warehouse (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inv_location ADD CONSTRAINT FK_A18C0012727ACA70 FOREIGN KEY (parent_id) REFERENCES inv_location (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_A18C00125080ECDE ON inv_location (warehouse_id)');
        $this->addSql('CREATE INDEX IDX_A18C0012727ACA70 ON inv_location (parent_id)');
        // Backfill existing records before enforcing the new JSON columns.
        foreach (['inv_product', 'inv_location', 'inv_movement'] as $table) {
            $this->addSql("UPDATE $table SET details = '{}' WHERE details IS NULL");
            $this->addSql("ALTER TABLE $table MODIFY details JSON NOT NULL");
        }
        $this->addSql('ALTER TABLE inv_product ALTER min_stock DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inv_category DROP FOREIGN KEY FK_F95E9018727ACA70');
        $this->addSql('ALTER TABLE inv_trace_balance DROP FOREIGN KEY FK_259A32844584665A');
        $this->addSql('ALTER TABLE inv_trace_balance DROP FOREIGN KEY FK_259A328464D218E');
        $this->addSql('ALTER TABLE inv_product DROP FOREIGN KEY FK_CEA9AD74727ACA70');
        $this->addSql('ALTER TABLE inv_product DROP FOREIGN KEY FK_CEA9AD7412469DE2');
        $this->addSql('DROP INDEX UNIQ_CEA9AD7497AE0266 ON inv_product');
        $this->addSql('DROP INDEX IDX_CEA9AD74727ACA70 ON inv_product');
        $this->addSql('DROP INDEX IDX_CEA9AD7412469DE2 ON inv_product');
        $this->addSql('ALTER TABLE inv_product DROP barcode, DROP min_stock, DROP details, DROP parent_id, DROP category_id');
        $this->addSql('ALTER TABLE inv_movement DROP details');
        $this->addSql('ALTER TABLE inv_location DROP FOREIGN KEY FK_A18C00125080ECDE');
        $this->addSql('ALTER TABLE inv_location DROP FOREIGN KEY FK_A18C0012727ACA70');
        $this->addSql('DROP INDEX IDX_A18C00125080ECDE ON inv_location');
        $this->addSql('DROP INDEX IDX_A18C0012727ACA70 ON inv_location');
        $this->addSql('ALTER TABLE inv_location DROP details, DROP warehouse_id, DROP parent_id');
        $this->addSql('DROP TABLE inv_category');
        $this->addSql('DROP TABLE inv_trace_balance');
        $this->addSql('DROP TABLE inv_warehouse');
    }
}
