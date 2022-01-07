<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200727182041 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE sym_url_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE sym_url (id INT NOT NULL, short_tag VARCHAR(255) NOT NULL, long_url TEXT NOT NULL, use_count INT NOT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_last_used TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_953A797574C9F71C ON sym_url (short_tag)');
        $this->addSql('CREATE INDEX long_url_idx ON sym_url (long_url)');
        $this->addSql('CREATE TABLE mddb_entity_type (id INT NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE mddb_entity_attribute (id INT NOT NULL, metadata_id INT DEFAULT NULL, key VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_67C4D266DC9EE959 ON mddb_entity_attribute (metadata_id)');
        $this->addSql('CREATE TABLE mddb_entity (id INT NOT NULL, type_id INT DEFAULT NULL, code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D1FDC522C54C8C93 ON mddb_entity (type_id)');
        $this->addSql('CREATE TABLE mddb_entity_relationship (from_id INT NOT NULL, to_id INT NOT NULL, PRIMARY KEY(from_id, to_id))');
        $this->addSql('CREATE INDEX IDX_93FEB9AB78CED90B ON mddb_entity_relationship (from_id)');
        $this->addSql('CREATE INDEX IDX_93FEB9AB30354A65 ON mddb_entity_relationship (to_id)');
        $this->addSql('ALTER TABLE mddb_entity_attribute ADD CONSTRAINT FK_67C4D266DC9EE959 FOREIGN KEY (metadata_id) REFERENCES mddb_entity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mddb_entity ADD CONSTRAINT FK_D1FDC522C54C8C93 FOREIGN KEY (type_id) REFERENCES mddb_entity_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mddb_entity_relationship ADD CONSTRAINT FK_93FEB9AB78CED90B FOREIGN KEY (from_id) REFERENCES mddb_entity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mddb_entity_relationship ADD CONSTRAINT FK_93FEB9AB30354A65 FOREIGN KEY (to_id) REFERENCES mddb_entity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE mddb_entity DROP CONSTRAINT FK_D1FDC522C54C8C93');
        $this->addSql('ALTER TABLE mddb_entity_attribute DROP CONSTRAINT FK_67C4D266DC9EE959');
        $this->addSql('ALTER TABLE mddb_entity_relationship DROP CONSTRAINT FK_93FEB9AB78CED90B');
        $this->addSql('ALTER TABLE mddb_entity_relationship DROP CONSTRAINT FK_93FEB9AB30354A65');
        $this->addSql('DROP SEQUENCE sym_url_id_seq CASCADE');
        $this->addSql('DROP TABLE sym_url');
        $this->addSql('DROP TABLE mddb_entity_type');
        $this->addSql('DROP TABLE mddb_entity_attribute');
        $this->addSql('DROP TABLE mddb_entity');
        $this->addSql('DROP TABLE mddb_entity_relationship');
    }
}
