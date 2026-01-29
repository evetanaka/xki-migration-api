<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250129000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables for XKI Migration: snapshots, claims, nonces';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE snapshots (
            ki_address VARCHAR(65) NOT NULL,
            balance VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(ki_address)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE claims (
            id INT AUTO_INCREMENT NOT NULL,
            ki_address VARCHAR(65) NOT NULL,
            eth_address VARCHAR(42) NOT NULL,
            amount VARCHAR(255) NOT NULL,
            signature LONGTEXT NOT NULL,
            pub_key VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            tx_hash VARCHAR(66) DEFAULT NULL,
            admin_notes LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            UNIQUE INDEX UNIQ_6F53AE26A1E1E8E7 (ki_address)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE nonces (
            nonce VARCHAR(64) NOT NULL,
            ki_address VARCHAR(65) NOT NULL,
            eth_address VARCHAR(42) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY(nonce)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE snapshots');
        $this->addSql('DROP TABLE claims');
        $this->addSql('DROP TABLE nonces');
    }
}
