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
        $this->assertEquals(['term' => 'string'], $searchField['args']);
    }

    public function testHandlesFieldDescriptions(): void
    {
        $requirements = $this->analyzer->getResolverRequirements();

        // The User type has a description in the schema
        $this->assertArrayHasKey('User.posts', $requirements);
        $userPosts = $requirements['User.posts'];
        $this->assertNull($userPosts['description']); // Field itself has no description
    }
}
