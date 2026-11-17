<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros; see docs/upstream/Inventoros-NOTICE.

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the scoped Inventoros inventory API tables without modifying existing tables.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'This migration targets MySQL/MariaDB, matching the existing migrations.');
        $this->addSql('CREATE TABLE inv_balance (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, created_at DATETIME NOT NULL, product_id INT NOT NULL, location_id INT NOT NULL, UNIQUE INDEX inv_balance_target (product_id, location_id), INDEX IDX_B117B6274584665A (product_id), INDEX IDX_B117B62764D218E (location_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inv_customer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, details JSON NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inv_document (id INT AUTO_INCREMENT NOT NULL, kind VARCHAR(30) NOT NULL, number VARCHAR(100) NOT NULL, status VARCHAR(30) NOT NULL, details JSON NOT NULL, items JSON NOT NULL, created_at DATETIME NOT NULL, customer_id INT DEFAULT NULL, supplier_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_277B03AF96901F54 (number), INDEX IDX_277B03AF9395C3F3 (customer_id), INDEX IDX_277B03AF2ADD6D8C (supplier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inv_location (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_A18C001277153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inv_movement (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, quantity_before INT NOT NULL, quantity_after INT NOT NULL, type VARCHAR(30) NOT NULL, reference VARCHAR(255) NOT NULL, reason LONGTEXT NOT NULL, created_at DATETIME NOT NULL, product_id INT NOT NULL, location_id INT NOT NULL, INDEX IDX_BCF1C2E4584665A (product_id), INDEX IDX_BCF1C2E64D218E (location_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inv_product (id INT AUTO_INCREMENT NOT NULL, sku VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, price VARCHAR(30) NOT NULL, cost VARCHAR(30) NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_CEA9AD74F9038C4 (sku), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inv_supplier (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, details JSON NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE inv_balance ADD CONSTRAINT FK_B117B6274584665A FOREIGN KEY (product_id) REFERENCES inv_product (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inv_balance ADD CONSTRAINT FK_B117B62764D218E FOREIGN KEY (location_id) REFERENCES inv_location (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inv_document ADD CONSTRAINT FK_277B03AF9395C3F3 FOREIGN KEY (customer_id) REFERENCES inv_customer (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inv_document ADD CONSTRAINT FK_277B03AF2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES inv_supplier (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inv_movement ADD CONSTRAINT FK_BCF1C2E4584665A FOREIGN KEY (product_id) REFERENCES inv_product (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inv_movement ADD CONSTRAINT FK_BCF1C2E64D218E FOREIGN KEY (location_id) REFERENCES inv_location (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inv_movement');
        $this->addSql('DROP TABLE inv_balance');
        $this->addSql('DROP TABLE inv_document');
        $this->addSql('DROP TABLE inv_product');
        $this->addSql('DROP TABLE inv_location');
        $this->addSql('DROP TABLE inv_customer');
        $this->addSql('DROP TABLE inv_supplier');
    }
}
