<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250513122035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE panier_product (panier_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_29F0C02CF77D927C (panier_id), INDEX IDX_29F0C02C4584665A (product_id), PRIMARY KEY(panier_id, product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE panier_product ADD CONSTRAINT FK_29F0C02CF77D927C FOREIGN KEY (panier_id) REFERENCES panier (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE panier_product ADD CONSTRAINT FK_29F0C02C4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE panier_product DROP FOREIGN KEY FK_29F0C02CF77D927C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE panier_product DROP FOREIGN KEY FK_29F0C02C4584665A
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE panier_product
        SQL);
    }
}
