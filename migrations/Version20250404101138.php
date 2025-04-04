<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250404101138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shopify_app_config (id INT AUTO_INCREMENT NOT NULL, shop_domain VARCHAR(255) NOT NULL, protocol VARCHAR(255) NOT NULL, server_url VARCHAR(255) NOT NULL, port INT NOT NULL, username VARCHAR(255) NOT NULL, root_directory VARCHAR(255) NOT NULL, private_key_content LONGTEXT NOT NULL, key_passphrase VARCHAR(255) NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE shopify_app_config');
    }
}
