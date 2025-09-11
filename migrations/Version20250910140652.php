<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250910140652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopify_app_config ADD ff_channel_name VARCHAR(255) DEFAULT NULL, ADD ff_api_server_url VARCHAR(255) DEFAULT NULL, ADD ff_api_username VARCHAR(255) DEFAULT NULL, ADD ff_api_password VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopify_app_config DROP ff_channel_name, DROP ff_api_server_url, DROP ff_api_username, DROP ff_api_password');
    }
}
