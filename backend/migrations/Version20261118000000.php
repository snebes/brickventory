<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add supplier catalogs and idempotent replenishment draft batches.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'This migration targets MySQL/MariaDB.');
        $this->addSql('CREATE TABLE inv_reorder_batch (id VARCHAR(36) NOT NULL, request_hash VARCHAR(64) NOT NULL, result JSON NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE inv_product_supplier (id INT AUTO_INCREMENT NOT NULL, supplier_sku VARCHAR(255) NOT NULL, cost_price VARCHAR(30) NOT NULL, currency VARCHAR(3) NOT NULL, minimum_order_quantity INT NOT NULL, lead_time_days INT NOT NULL, is_primary TINYINT NOT NULL, product_id INT NOT NULL, supplier_id INT NOT NULL, UNIQUE INDEX inv_product_supplier_pair (product_id, supplier_id), INDEX IDX_D03CA0E34584665A (product_id), INDEX IDX_D03CA0E32ADD6D8C (supplier_id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE inv_product_supplier ADD CONSTRAINT FK_D03CA0E34584665A FOREIGN KEY (product_id) REFERENCES inv_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inv_product_supplier ADD CONSTRAINT FK_D03CA0E32ADD6D8C FOREIGN KEY (supplier_id) REFERENCES inv_supplier (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inv_product_supplier DROP FOREIGN KEY FK_D03CA0E34584665A');
        $this->addSql('ALTER TABLE inv_product_supplier DROP FOREIGN KEY FK_D03CA0E32ADD6D8C');
        $this->addSql('DROP TABLE inv_reorder_batch');
        $this->addSql('DROP TABLE inv_product_supplier');
    }
}
