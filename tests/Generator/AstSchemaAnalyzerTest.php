<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Test\Generator;

use GraphQL\Language\Parser;
use GraphQL\Middleware\Generator\AstSchemaAnalyzer;
use GraphQL\Middleware\Generator\DefaultTypeMapper;
use PHPUnit\Framework\TestCase;

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

    protected function setUp(): void
    {
        $document = Parser::parse(self::TEST_SCHEMA);
        $this->analyzer = new AstSchemaAnalyzer($document, new DefaultTypeMapper());
    }

    public function testGetResolverRequirements(): void
    {
        $requirements = $this->analyzer->getResolverRequirements();

        $this->assertIsArray($requirements);
        $this->assertNotEmpty($requirements);

        // Test Query.user resolver
        $this->assertArrayHasKey('Query.user', $requirements);
        $userReq = $requirements['Query.user'];
        $this->assertEquals('Query', $userReq['type']);
        $this->assertEquals('user', $userReq['field']);
        $this->assertEquals('User', $userReq['returnType']);
        $this->assertArrayHasKey('id', $userReq['args']);
        $this->assertEquals('string', $userReq['args']['id']);

        // Test Query.users resolver
        $this->assertArrayHasKey('Query.users', $requirements);
        $usersReq = $requirements['Query.users'];
        $this->assertEquals('Query', $usersReq['type']);
        $this->assertEquals('users', $usersReq['field']);
        $this->assertEquals('User|null', $usersReq['returnType']);
        $this->assertEmpty($usersReq['args']);

        // Test User.posts resolver
        $this->assertArrayHasKey('User.posts', $requirements);
        $postsReq = $requirements['User.posts'];
        $this->assertEquals('User', $postsReq['type']);
        $this->assertEquals('posts', $postsReq['field']);
        $this->assertEquals('Post|null', $postsReq['returnType']);
        $this->assertEmpty($postsReq['args']);

        // Test User.friends resolver (nullable array of nullable User)
        $this->assertArrayHasKey('User.friends', $requirements);
        $friendsReq = $requirements['User.friends'];
        $this->assertEquals('User', $friendsReq['type']);
        $this->assertEquals('friends', $friendsReq['field']);
        $this->assertEquals('User|null', $friendsReq['returnType']);
        $this->assertEmpty($friendsReq['args']);

        // Test Post.author resolver
        $this->assertArrayHasKey('Post.author', $requirements);
        $authorReq = $requirements['Post.author'];
        $this->assertEquals('Post', $authorReq['type']);
        $this->assertEquals('author', $authorReq['field']);
        $this->assertEquals('User', $authorReq['returnType']);
        $this->assertEmpty($authorReq['args']);

        // Test that scalar fields are not included
        $this->assertArrayNotHasKey('User.name', $requirements);
        $this->assertArrayNotHasKey('User.age', $requirements);
        $this->assertArrayNotHasKey('Post.title', $requirements);
        $this->assertArrayNotHasKey('Post.content', $requirements);

        // Test that input types are not included
        $this->assertArrayNotHasKey('UserInput', $requirements);
    }

    public function testHandlesSearchResultInterface(): void
    {
        $requirements = $this->analyzer->getResolverRequirements();

        $this->assertArrayHasKey('Query.search', $requirements);
        $searchReq = $requirements['Query.search'];
        $this->assertEquals('Query', $searchReq['type']);
        $this->assertEquals('search', $searchReq['field']);
        $this->assertEquals('SearchResult', $searchReq['returnType']);
        $this->assertArrayHasKey('term', $searchReq['args']);
        $this->assertEquals('string', $searchReq['args']['term']);

        // Test interface implementations
        $this->assertArrayHasKey('UserSearchResult.user', $requirements);
        $this->assertArrayHasKey('PostSearchResult.post', $requirements);
    }

    public function testHandlesFieldDescriptions(): void
    {
        $requirements = $this->analyzer->getResolverRequirements();

        // The User type has a description
        $this->assertArrayHasKey('Query.user', $requirements);
        $this->assertNull(
            $requirements['Query.user']['description'],
            'Description should be null as it is on the type, not the field'
        );
    }
}
