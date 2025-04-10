# Slim 4 Example

This example demonstrates how to use the PSR-15 GraphQL Middleware with Slim 4.

## Installation

1. Install dependencies:
```bash
composer install
```

2. Run the application:
```bash
php -S localhost:8080 index.php
```

3. Test the GraphQL endpoint:
```bash
curl -X POST http://localhost:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ hello }"}'
```
