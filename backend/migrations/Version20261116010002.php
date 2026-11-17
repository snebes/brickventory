<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116010002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique customer and supplier codes.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'This migration targets MySQL/MariaDB.');
        $this->addSql('ALTER TABLE inv_customer ADD code VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7E2B07D077153098 ON inv_customer (code)');
        $this->addSql('ALTER TABLE inv_supplier ADD code VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6438E5A777153098 ON inv_supplier (code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_7E2B07D077153098 ON inv_customer');
        $this->addSql('ALTER TABLE inv_customer DROP code');
        $this->addSql('DROP INDEX UNIQ_6438E5A777153098 ON inv_supplier');
        $this->addSql('ALTER TABLE inv_supplier DROP code');
    }
}
