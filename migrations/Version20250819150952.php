<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250819150952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mouvement_caisse ADD demande_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mouvement_caisse ADD CONSTRAINT FK_C8E3DDFE80E95E18 FOREIGN KEY (demande_id) REFERENCES demande (id)');
        $this->addSql('CREATE INDEX IDX_C8E3DDFE80E95E18 ON mouvement_caisse (demande_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mouvement_caisse DROP FOREIGN KEY FK_C8E3DDFE80E95E18');
        $this->addSql('DROP INDEX IDX_C8E3DDFE80E95E18 ON mouvement_caisse');
        $this->addSql('ALTER TABLE mouvement_caisse DROP demande_id');
    }
}
