<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423NftClaim extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create NFT claim tables: nft_asset, nft_claim, nft_claim_config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE nft_claim (
            id SERIAL PRIMARY KEY,
            ki_address VARCHAR(64) NOT NULL UNIQUE,
            eth_address VARCHAR(42) NOT NULL,
            total_allocation DECIMAL(18,6) NOT NULL,
            nft_count INTEGER NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT \'pending\',
            signature TEXT NOT NULL,
            pub_key TEXT,
            nonce VARCHAR(64) NOT NULL,
            signed_message TEXT NOT NULL,
            tx_hash VARCHAR(66),
            created_at TIMESTAMP NOT NULL DEFAULT NOW(),
            processed_at TIMESTAMP
        )');

        $this->addSql('CREATE TABLE nft_asset (
            id SERIAL PRIMARY KEY,
            collection VARCHAR(32) NOT NULL,
            token_id VARCHAR(64) NOT NULL,
            owner VARCHAR(64) NOT NULL,
            name VARCHAR(255) NOT NULL,
            image VARCHAR(512) NOT NULL,
            scarcity VARCHAR(32) NOT NULL,
            personality VARCHAR(64),
            geographical VARCHAR(64),
            time VARCHAR(64),
            nationality VARCHAR(64),
            asset_id VARCHAR(64),
            short_description TEXT,
            allocation DECIMAL(18,6) NOT NULL DEFAULT 0,
            claim_id INTEGER REFERENCES nft_claim(id),
            created_at TIMESTAMP NOT NULL DEFAULT NOW(),
            UNIQUE(collection, token_id)
        )');

        $this->addSql('CREATE INDEX idx_nft_asset_owner ON nft_asset(owner)');

        $this->addSql('CREATE TABLE nft_claim_config (
            key VARCHAR(64) PRIMARY KEY,
            value TEXT NOT NULL
        )');

        // Seed default config
        $this->addSql("INSERT INTO nft_claim_config (key, value) VALUES 
            ('deadline', '2026-07-01T00:00:00Z'),
            ('pool_total', '5000000'),
            ('enabled', 'true')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS nft_asset');
        $this->addSql('DROP TABLE IF EXISTS nft_claim');
        $this->addSql('DROP TABLE IF EXISTS nft_claim_config');
    }
}
