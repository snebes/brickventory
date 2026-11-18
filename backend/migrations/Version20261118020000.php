<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add durable checkpointed background product imports.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'This migration targets MySQL/MariaDB.');
        $this->addSql('CREATE TABLE inv_product_import (id VARCHAR(36) NOT NULL, filename VARCHAR(255) NOT NULL, status VARCHAR(30) NOT NULL, file_size INT NOT NULL, `cursor` BIGINT NOT NULL, processed INT NOT NULL, records_read INT NOT NULL, header JSON NOT NULL, error JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inv_product_import');
    }
}
