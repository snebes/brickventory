<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cycle count schedules and serial lifecycle registry.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'This migration targets MySQL/MariaDB.');
        $this->addSql('CREATE TABLE inv_product_serial (id INT AUTO_INCREMENT NOT NULL, serial_number VARCHAR(100) NOT NULL, status VARCHAR(30) NOT NULL, notes LONGTEXT NOT NULL, created_at DATETIME NOT NULL, product_id INT NOT NULL, UNIQUE INDEX inv_serial_identity (product_id, serial_number), INDEX IDX_7C47BC854584665A (product_id), PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE inv_cycle_count_schedule (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, details JSON NOT NULL, is_active TINYINT NOT NULL, next_run_at DATETIME NOT NULL, last_run_at DATETIME DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE inv_product_serial ADD CONSTRAINT FK_7C47BC854584665A FOREIGN KEY (product_id) REFERENCES inv_product (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inv_product_serial DROP FOREIGN KEY FK_7C47BC854584665A');
        $this->addSql('DROP TABLE inv_product_serial');
        $this->addSql('DROP TABLE inv_cycle_count_schedule');
    }
}
