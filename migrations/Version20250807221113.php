<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250807221113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, categorie_id INT NOT NULL, code VARCHAR(255) NOT NULL, tva DOUBLE PRECISION NOT NULL, prix_unitaire DOUBLE PRECISION NOT NULL, UNIQUE INDEX UNIQ_23A0E6677153098 (code), INDEX IDX_23A0E66BCF5E72D (categorie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE categorie_article (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE categorie_client (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, categorie_id INT NOT NULL, code VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, prix DOUBLE PRECISION NOT NULL, UNIQUE INDEX UNIQ_C744045577153098 (code), INDEX IDX_C7440455BCF5E72D (categorie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE demande (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, article_id INT NOT NULL, status_id INT NOT NULL, date DATE NOT NULL, quantite INT NOT NULL, montant DOUBLE PRECISION NOT NULL, INDEX IDX_2694D7A519EB6921 (client_id), INDEX IDX_2694D7A57294869C (article_id), INDEX IDX_2694D7A56BF700BD (status_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE status_demande (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie_article (id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie_client (id)');
        $this->addSql('ALTER TABLE demande ADD CONSTRAINT FK_2694D7A519EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE demande ADD CONSTRAINT FK_2694D7A57294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE demande ADD CONSTRAINT FK_2694D7A56BF700BD FOREIGN KEY (status_id) REFERENCES status_demande (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66BCF5E72D');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455BCF5E72D');
        $this->addSql('ALTER TABLE demande DROP FOREIGN KEY FK_2694D7A519EB6921');
        $this->addSql('ALTER TABLE demande DROP FOREIGN KEY FK_2694D7A57294869C');
        $this->addSql('ALTER TABLE demande DROP FOREIGN KEY FK_2694D7A56BF700BD');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE categorie_article');
        $this->addSql('DROP TABLE categorie_client');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE demande');
        $this->addSql('DROP TABLE status_demande');
    }
}
