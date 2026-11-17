<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116010001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product image and document attachments.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'This migration targets MySQL/MariaDB.');
        $this->addSql('CREATE TABLE inv_attachment (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, content MEDIUMBLOB NOT NULL, created_at DATETIME NOT NULL, product_id INT NOT NULL, INDEX IDX_BDC1637B4584665A (product_id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE inv_attachment ADD CONSTRAINT FK_BDC1637B4584665A FOREIGN KEY (product_id) REFERENCES inv_product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inv_attachment DROP FOREIGN KEY FK_BDC1637B4584665A');
        $this->addSql('DROP TABLE inv_attachment');
    }
}
