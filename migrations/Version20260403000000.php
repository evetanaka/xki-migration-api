<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add team wallet fields: isTeam, initialAmountDistributed, slashedAmount';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE claims ADD COLUMN is_team BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE claims ADD COLUMN initial_amount_distributed BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE claims ADD COLUMN slashed_amount BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE claims ADD COLUMN original_amount BIGINT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_claims_is_team ON claims (is_team)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_claims_is_team');
        $this->addSql('ALTER TABLE claims DROP COLUMN IF EXISTS is_team');
        $this->addSql('ALTER TABLE claims DROP COLUMN IF EXISTS initial_amount_distributed');
        $this->addSql('ALTER TABLE claims DROP COLUMN IF EXISTS slashed_amount');
        $this->addSql('ALTER TABLE claims DROP COLUMN IF EXISTS original_amount');
    }
}
