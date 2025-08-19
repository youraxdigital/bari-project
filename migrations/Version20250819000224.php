<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250819000224 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE caisse (id INT AUTO_INCREMENT NOT NULL, opened_at DATETIME NOT NULL, montant_initial DOUBLE PRECISION NOT NULL, montant_actuel DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mouvement_caisse (id INT AUTO_INCREMENT NOT NULL, caisse_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, motif VARCHAR(255) NOT NULL, montant DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_C8E3DDFE27B4FEBF (caisse_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE mouvement_caisse ADD CONSTRAINT FK_C8E3DDFE27B4FEBF FOREIGN KEY (caisse_id) REFERENCES caisse (id)');
        $this->addSql('ALTER TABLE demande ADD caisse_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE demande ADD CONSTRAINT FK_2694D7A527B4FEBF FOREIGN KEY (caisse_id) REFERENCES caisse (id)');
        $this->addSql('CREATE INDEX IDX_2694D7A527B4FEBF ON demande (caisse_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande DROP FOREIGN KEY FK_2694D7A527B4FEBF');
        $this->addSql('ALTER TABLE mouvement_caisse DROP FOREIGN KEY FK_C8E3DDFE27B4FEBF');
        $this->addSql('DROP TABLE caisse');
        $this->addSql('DROP TABLE mouvement_caisse');
        $this->addSql('DROP INDEX IDX_2694D7A527B4FEBF ON demande');
        $this->addSql('ALTER TABLE demande DROP caisse_id');
    }
}
