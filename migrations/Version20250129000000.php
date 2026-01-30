<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250129000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables for XKI Migration: snapshots, claims, nonces';
    }

    public function up(Schema $schema): void
    {
        // PostgreSQL syntax
        $this->addSql('CREATE TABLE snapshots (
            ki_address VARCHAR(65) NOT NULL,
            balance VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL,
            PRIMARY KEY(ki_address)
        )');

        $this->addSql('CREATE TABLE claims (
            id SERIAL NOT NULL,
            ki_address VARCHAR(65) NOT NULL,
            eth_address VARCHAR(42) NOT NULL,
            amount VARCHAR(255) NOT NULL,
            signature TEXT NOT NULL,
            pub_key VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            tx_hash VARCHAR(66) DEFAULT NULL,
            admin_notes TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_claims_ki_address ON claims (ki_address)');

        $this->addSql('CREATE TABLE nonces (
            nonce VARCHAR(64) NOT NULL,
            ki_address VARCHAR(65) NOT NULL,
            eth_address VARCHAR(42) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used BOOLEAN DEFAULT FALSE NOT NULL,
            PRIMARY KEY(nonce)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE snapshots');
        $this->addSql('DROP TABLE claims');
        $this->addSql('DROP TABLE nonces');
    }
}
