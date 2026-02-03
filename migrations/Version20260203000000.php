<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add governance tables: proposals and votes';
    }

    public function up(Schema $schema): void
    {
        // Proposals table
        $this->addSql('CREATE TABLE proposals (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NOT NULL,
            status VARCHAR(50) NOT NULL,
            proposal_number VARCHAR(255) NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            votes_for VARCHAR(255) NOT NULL DEFAULT "0",
            votes_against VARCHAR(255) NOT NULL DEFAULT "0",
            votes_abstain VARCHAR(255) NOT NULL DEFAULT "0",
            voter_count INT NOT NULL DEFAULT 0,
            quorum VARCHAR(255) NOT NULL DEFAULT "0",
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            INDEX idx_proposals_status (status),
            INDEX idx_proposals_dates (start_date, end_date)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Votes table
        $this->addSql('CREATE TABLE votes (
            id INT AUTO_INCREMENT NOT NULL,
            proposal_id INT NOT NULL,
            ki_address VARCHAR(255) NOT NULL,
            vote_choice VARCHAR(20) NOT NULL,
            voting_power VARCHAR(255) NOT NULL,
            signature LONGTEXT NOT NULL,
            pub_key LONGTEXT NOT NULL,
            voted_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            UNIQUE INDEX unique_vote (proposal_id, ki_address),
            INDEX idx_votes_address (ki_address),
            CONSTRAINT FK_votes_proposal FOREIGN KEY (proposal_id) REFERENCES proposals (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE votes');
        $this->addSql('DROP TABLE proposals');
    }
}
