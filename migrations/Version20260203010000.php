<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate governance tables with correct PostgreSQL syntax';
    }

    public function up(Schema $schema): void
    {
        // Drop existing tables if they exist (clean slate)
        $this->addSql('DROP TABLE IF EXISTS votes CASCADE');
        $this->addSql('DROP TABLE IF EXISTS proposals CASCADE');
        
        // Proposals table (PostgreSQL syntax)
        $this->addSql('CREATE TABLE proposals (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'draft\',
            proposal_number VARCHAR(255) NOT NULL,
            start_date TIMESTAMP NOT NULL,
            end_date TIMESTAMP NOT NULL,
            votes_for VARCHAR(255) NOT NULL DEFAULT \'0\',
            votes_against VARCHAR(255) NOT NULL DEFAULT \'0\',
            votes_abstain VARCHAR(255) NOT NULL DEFAULT \'0\',
            voter_count INT NOT NULL DEFAULT 0,
            quorum VARCHAR(255) NOT NULL DEFAULT \'0\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        
        $this->addSql('CREATE INDEX idx_proposals_status ON proposals (status)');
        $this->addSql('CREATE INDEX idx_proposals_dates ON proposals (start_date, end_date)');

        // Votes table (PostgreSQL syntax)
        $this->addSql('CREATE TABLE votes (
            id SERIAL PRIMARY KEY,
            proposal_id INT NOT NULL,
            ki_address VARCHAR(255) NOT NULL,
            vote_choice VARCHAR(20) NOT NULL,
            voting_power VARCHAR(255) NOT NULL,
            signature TEXT NOT NULL,
            pub_key TEXT NOT NULL,
            voted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_votes_proposal FOREIGN KEY (proposal_id) REFERENCES proposals (id) ON DELETE CASCADE
        )');
        
        $this->addSql('CREATE UNIQUE INDEX unique_vote ON votes (proposal_id, ki_address)');
        $this->addSql('CREATE INDEX idx_votes_address ON votes (ki_address)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS votes CASCADE');
        $this->addSql('DROP TABLE IF EXISTS proposals CASCADE');
    }
}
