# GraphQL Middleware Mezzio Example

This example demonstrates how to use the GraphQL Middleware with Mezzio.

## Installation

1. Install dependencies:
```bash
composer install
```

2. Start the server:
```bash
php -S localhost:8080 -t public
```

## Usage

The GraphQL endpoint is available at `/`. You can send GraphQL queries using POST requests with `Content-Type: application/json`.

### Example Queries

1. Hello World:
```graphql
{
  hello
}
```

2. Echo:
```graphql
{
  echo(message: "Hello GraphQL!")
}
```

3. Greet (Mutation):
```graphql
mutation {
  greet(name: "John")
}
```

## Testing

You can use tools like [Altair GraphQL Client](https://altair.sirmuel.design/) or [GraphiQL](https://github.com/graphql/graphiql) to test the API.
