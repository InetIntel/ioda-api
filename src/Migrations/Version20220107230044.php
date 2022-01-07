i<?php

declare(strict_types=1);
    
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**     
 * Auto-generated Migration: Please modify to your needs!
 */ 
final class Version20220107230044 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE alerts_with_entity_view (id INT NOT NULL, meta_id INT DEFAULT NULL, fqid VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, query_time INT NOT NULL, time INT NOT NULL, level VARCHAR(255) NOT NULL, method VARCHAR(255) NOT NULL, condition VARCHAR(255) NOT NULL, value DOUBLE PRECISION NOT NULL, history_value DOUBLE PRECISION NOT NULL, meta_type VARCHAR(255) NOT NULL, meta_code VARCHAR(255) NOT NULL, related_type VARCHAR(255) NOT NULL, related_code VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BEAD7D9F39FCA6F9 ON alerts_with_entity_view (meta_id)');
        $this->addSql('ALTER TABLE alerts_with_entity_view ADD CONSTRAINT FK_BEAD7D9F39FCA6F9 FOREIGN KEY (meta_id) REFERENCES mddb_entity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE alerts_with_entity_view');
    }
}

