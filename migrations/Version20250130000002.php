<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250130000002 extends AbstractMigration
{
    public function getDescription(): string { return 'Add nonce column to claims'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE claims ADD COLUMN nonce VARCHAR(64) DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE claims DROP COLUMN nonce');
    }
}
