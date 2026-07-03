<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260120172235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inventory_adjustment ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE inventory_adjustment RENAME INDEX idx_f7cefc7164d218e TO IDX_CA172D9564D218E');
        $this->addSql('ALTER TABLE item CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE active active TINYINT NOT NULL');
        $this->addSql('ALTER TABLE item_category CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE active active TINYINT NOT NULL');
        $this->addSql('ALTER TABLE item_fulfillment ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE item_receipt ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE landed_cost ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE physical_count ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE sales_order ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inventory_adjustment DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE inventory_adjustment RENAME INDEX idx_ca172d9564d218e TO IDX_F7CEFC7164D218E');
        $this->addSql('ALTER TABLE item CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE active active TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE item_category CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE active active TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE item_fulfillment DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE item_receipt DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE landed_cost DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE physical_count DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE purchase_order DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE sales_order DROP created_at, DROP updated_at');
    }
}
