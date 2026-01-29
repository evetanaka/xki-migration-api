# XKI Migration API

Backend API pour la migration XKI (Ki Chain → Ethereum).

## Stack
- PHP 8.4
- Symfony 8.0
- PostgreSQL
- Doctrine ORM

## Features
- Vérification d'éligibilité via snapshot
- Validation de signature Cosmos (secp256k1)
- Gestion des claims (CRUD)
- Admin dashboard
- API REST

## Installation

```bash
composer install
cp .env .env.local
# Configure DATABASE_URL in .env.local
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

## API Endpoints

See `/docs/api.md` for full API documentation.

## License

Proprietary - Ki Chain Foundation
