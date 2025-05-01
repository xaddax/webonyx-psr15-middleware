<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Test\Generator;

use GraphQL\Language\Parser;
use GraphQL\Middleware\Contract\SchemaConfigurationInterface;
use GraphQL\Middleware\Generator\AstSchemaAnalyzer;
use GraphQL\Middleware\Generator\DefaultTypeMapper;
use GraphQL\Middleware\Factory\GeneratedSchemaFactory;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use org\bovigo\vfs\vfsStream;

class AstSchemaAnalyzerTest extends TestCase
{
    private const TEST_SCHEMA = <<<'GRAPHQL'
type Query {
    user(id: ID!): User
    users: [User!]!
    search(term: String): SearchResult
}

"""
A user in the system
"""
type User {
    id: ID!
    name: String!
    age: Int
    posts: [Post!]
    friends: [User]
}

type Post {
    id: ID!
    title: String!
    content: String
    author: User!
}

scalar DateTime

interface SearchResult {
    id: ID!
}

type UserSearchResult implements SearchResult {
    id: ID!
    user: User!
}

type PostSearchResult implements SearchResult {
    id: ID!
    post: Post!
}

input UserInput {
    name: String!
    age: Int
}
GRAPHQL;

    private AstSchemaAnalyzer $analyzer;
    private GeneratedSchemaFactory $schemaFactory;
    private DefaultTypeMapper $typeMapper;
    private SchemaConfigurationInterface&MockObject $config;
    private \org\bovigo\vfs\vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('test', 0777);

        // Create schema directory and file
        $schemaDir = vfsStream::newDirectory('schema')->at($this->root);
        $schemaPath = $schemaDir->url() . '/schema.graphql';
        file_put_contents($schemaPath, self::TEST_SCHEMA);

        // Create cache directory
        $cacheDir = vfsStream::newDirectory('cache')->at($this->root);

        // Set up configuration
        $this->config = $this->createMock(SchemaConfigurationInterface::class);
        $this->config->expects($this->any())
            ->method('getSchemaDirectories')
            ->willReturn([$schemaDir->url()]);
        $this->config->expects($this->any())
            ->method('isCacheEnabled')
            ->willReturn(false);
        $this->config->expects($this->any())
            ->method('getCacheDirectory')
            ->willReturn($cacheDir->url());
        $this->config->expects($this->any())
            ->method('getDirectoryChangeFilename')
            ->willReturn('directory-changes.php');
        $this->config->expects($this->any())
            ->method('getSchemaFilename')
            ->willReturn('schema.php');
        $this->config->expects($this->any())
            ->method('getParserOptions')
            ->willReturn([]);

        $this->typeMapper = new DefaultTypeMapper();
        $this->schemaFactory = new GeneratedSchemaFactory($this->config);
        $this->analyzer = new AstSchemaAnalyzer($this->schemaFactory, $this->typeMapper);
    }

    public function testGetResolverRequirements(): void
    {
        $requirements = $this->analyzer->getResolverRequirements();

        $this->assertIsArray($requirements);
        $this->assertNotEmpty($requirements);

        // Test Query.user field
        $this->assertArrayHasKey('Query.user', $requirements);
        $userField = $requirements['Query.user'];
        $this->assertEquals('Query', $userField['type']);
        $this->assertEquals('user', $userField['field']);
        $this->assertEquals('User|null', $userField['returnType']);
        $this->assertEquals(['id' => 'string'], $userField['args']);

        // Test User.posts field
        $this->assertArrayHasKey('User.posts', $requirements);
        $postsField = $requirements['User.posts'];
        $this->assertEquals('User', $postsField['type']);
        $this->assertEquals('posts', $postsField['field']);
        $this->assertEquals('array<Post>|null', $postsField['returnType']);
        $this->assertEquals([], $postsField['args']);

        // Test search field with interface return type
        $this->assertArrayHasKey('Query.search', $requirements);
        $searchField = $requirements['Query.search'];
        $this->assertEquals('Query', $searchField['type']);
        $this->assertEquals('search', $searchField['field']);
        $this->assertEquals('SearchResult|null', $searchField['returnType']);
        $this->assertEquals(['term' => 'string|null'], $searchField['args']);
    }

    public function testHandlesFieldDescriptions(): void
    {
        $requirements = $this->analyzer->getResolverRequirements();

        // The User type has a description in the schema
        $this->assertArrayHasKey('User.posts', $requirements);
        $userPosts = $requirements['User.posts'];
        $this->assertNull($userPosts['description']); // Field itself has no description
    }

    public function testGetRequestRequirements(): void
    {
        $requirements = $this->analyzer->getRequestRequirements();

        $this->assertIsArray($requirements);
        $this->assertNotEmpty($requirements);

        // Test UserInput type
        $this->assertArrayHasKey('UserInput', $requirements);
        $userInput = $requirements['UserInput'];

        $this->assertEquals('UserInput', $userInput['name']);
        $this->assertNull($userInput['description']);

        // Test fields
        $this->assertArrayHasKey('name', $userInput['fields']);
        $this->assertArrayHasKey('age', $userInput['fields']);

        $this->assertEquals('string', $userInput['fields']['name']);
        $this->assertEquals('int|null', $userInput['fields']['age']);
    }

    public function testGetRequestRequirementsWithComplexInput(): void
    {
        $schema = <<<'GRAPHQL'
"""
Input for creating a new user
"""
input CreateUserInput {
    name: String!
    age: Int
    address: AddressInput!
    tags: [String!]
    roles: [RoleInput!]!
}

input AddressInput {
    street: String!
    city: String!
    country: String!
}

input RoleInput {
    name: String!
    permissions: [String!]!
}
GRAPHQL;

        // Create new schema file
        $schemaPath = $this->root->getChild('schema')->url() . '/complex-schema.graphql';
        file_put_contents($schemaPath, $schema);

        // Create new analyzer with the complex schema
        $this->config->expects($this->any())
            ->method('getSchemaDirectories')
            ->willReturn([$this->root->getChild('schema')->url()]);
        $schemaFactory = new GeneratedSchemaFactory($this->config);
        $analyzer = new AstSchemaAnalyzer($schemaFactory, $this->typeMapper);

        $requirements = $analyzer->getRequestRequirements();

        // Test CreateUserInput
        $this->assertArrayHasKey('CreateUserInput', $requirements);
        $createUserInput = $requirements['CreateUserInput'];

        $this->assertEquals('CreateUserInput', $createUserInput['name']);
        $this->assertEquals('Input for creating a new user', $createUserInput['description']);

        // Test fields
        $this->assertArrayHasKey('name', $createUserInput['fields']);
        $this->assertArrayHasKey('age', $createUserInput['fields']);
        $this->assertArrayHasKey('address', $createUserInput['fields']);
        $this->assertArrayHasKey('tags', $createUserInput['fields']);
        $this->assertArrayHasKey('roles', $createUserInput['fields']);

        $this->assertEquals('string', $createUserInput['fields']['name']);
        $this->assertEquals('int|null', $createUserInput['fields']['age']);
        $this->assertEquals('AddressInput', $createUserInput['fields']['address']);
        $this->assertEquals('array<string>|null', $createUserInput['fields']['tags']);
        $this->assertEquals('array<RoleInput>', $createUserInput['fields']['roles']);

        // Test AddressInput
        $this->assertArrayHasKey('AddressInput', $requirements);
        $addressInput = $requirements['AddressInput'];

        $this->assertEquals('AddressInput', $addressInput['name']);
        $this->assertNull($addressInput['description']);

        $this->assertEquals('string', $addressInput['fields']['street']);
        $this->assertEquals('string', $addressInput['fields']['city']);
        $this->assertEquals('string', $addressInput['fields']['country']);

        // Test RoleInput
        $this->assertArrayHasKey('RoleInput', $requirements);
        $roleInput = $requirements['RoleInput'];

        $this->assertEquals('RoleInput', $roleInput['name']);
        $this->assertEquals('string', $roleInput['fields']['name']);
        $this->assertEquals('array<string>', $roleInput['fields']['permissions']);
    }

    public function testHandlesEmptySchema(): void
    {
        $schema = <<<'GRAPHQL'
input EmptyInput {
    unused: String
}
GRAPHQL;

        $schemaPath = $this->root->getChild('schema')->url() . '/empty-schema.graphql';
        file_put_contents($schemaPath, $schema);

        $this->config->expects($this->any())
            ->method('getSchemaDirectories')
            ->willReturn([$this->root->getChild('schema')->url()]);
        $schemaFactory = new GeneratedSchemaFactory($this->config);
        $analyzer = new AstSchemaAnalyzer($schemaFactory, $this->typeMapper);

        $requirements = $analyzer->getRequestRequirements();
        $this->assertArrayHasKey('EmptyInput', $requirements);
        $this->assertArrayHasKey('unused', $requirements['EmptyInput']['fields']);
        $this->assertEquals('string|null', $requirements['EmptyInput']['fields']['unused']);
    }

    public function testHandlesInvalidAst(): void
    {
        $this->expectException(\GraphQL\Error\SyntaxError::class);
        $this->expectExceptionMessage('Syntax Error: Unexpected <EOF>');

        $mockSchemaFactory = $this->createMock(GeneratedSchemaFactory::class);
        $mockSchemaFactory->expects($this->once())
            ->method('createSchema')
            ->willReturn(new \GraphQL\Type\Schema([]));

        new AstSchemaAnalyzer($mockSchemaFactory, $this->typeMapper);
    }
}
