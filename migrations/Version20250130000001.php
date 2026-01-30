<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250130000001 extends AbstractMigration
{
    public function getDescription(): string { return 'Add eligibility table'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE eligibility (
            id SERIAL PRIMARY KEY,
            ki_address VARCHAR(255) NOT NULL UNIQUE,
            balance VARCHAR(255) NOT NULL,
            eligible BOOLEAN NOT NULL DEFAULT TRUE,
            claimed BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE eligibility'); }
}
