<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250513115726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE product_season (product_id INT NOT NULL, season_id INT NOT NULL, INDEX IDX_92981A0D4584665A (product_id), INDEX IDX_92981A0D4EC001D1 (season_id), PRIMARY KEY(product_id, season_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_season ADD CONSTRAINT FK_92981A0D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_season ADD CONSTRAINT FK_92981A0D4EC001D1 FOREIGN KEY (season_id) REFERENCES season (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ADD image_url VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE product_season DROP FOREIGN KEY FK_92981A0D4584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_season DROP FOREIGN KEY FK_92981A0D4EC001D1
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE product_season
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP image_url
        SQL);
    }
}
