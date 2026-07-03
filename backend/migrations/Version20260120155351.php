<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260120155351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item_receipt DROP FOREIGN KEY `FK_58C490116C1B716`');
        $this->addSql('DROP INDEX IDX_58C490116C1B716 ON item_receipt');
        $this->addSql('ALTER TABLE item_receipt ADD location_id INT NOT NULL, DROP received_at_location_id');
        $this->addSql('ALTER TABLE item_receipt ADD CONSTRAINT FK_58C4901164D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_58C4901164D218E ON item_receipt (location_id)');
        $this->addSql('ALTER TABLE purchase_order DROP FOREIGN KEY `FK_21E210B21F4BA9E3`');
        $this->addSql('DROP INDEX IDX_21E210B21F4BA9E3 ON purchase_order');
        $this->addSql('ALTER TABLE purchase_order ADD location_id INT NOT NULL, DROP ship_to_location_id, CHANGE vendor_id vendor_id INT NOT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT FK_21E210B264D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_21E210B264D218E ON purchase_order (location_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item_receipt DROP FOREIGN KEY FK_58C4901164D218E');
        $this->addSql('DROP INDEX IDX_58C4901164D218E ON item_receipt');
        $this->addSql('ALTER TABLE item_receipt ADD received_at_location_id INT DEFAULT NULL, DROP location_id');
        $this->addSql('ALTER TABLE item_receipt ADD CONSTRAINT `FK_58C490116C1B716` FOREIGN KEY (received_at_location_id) REFERENCES location (id)');
        $this->addSql('CREATE INDEX IDX_58C490116C1B716 ON item_receipt (received_at_location_id)');
        $this->addSql('ALTER TABLE purchase_order DROP FOREIGN KEY FK_21E210B264D218E');
        $this->addSql('DROP INDEX IDX_21E210B264D218E ON purchase_order');
        $this->addSql('ALTER TABLE purchase_order ADD ship_to_location_id INT DEFAULT NULL, DROP location_id, CHANGE vendor_id vendor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT `FK_21E210B21F4BA9E3` FOREIGN KEY (ship_to_location_id) REFERENCES location (id)');
        $this->addSql('CREATE INDEX IDX_21E210B21F4BA9E3 ON purchase_order (ship_to_location_id)');
    }
}
