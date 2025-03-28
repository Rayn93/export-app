<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250327092209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set correct datatime type columns in shopify_oauth_token';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shopify_oauth_token CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shopify_oauth_token CHANGE created_at created_at VARCHAR(255) NOT NULL, CHANGE updated_at updated_at VARCHAR(255) DEFAULT NULL');
    }
}
