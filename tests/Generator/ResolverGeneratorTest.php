<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Generator;

use GraphQL\Language\Parser;
use GraphQL\Middleware\Config\SchemaConfig;
use GraphQL\Middleware\Exception\GeneratorException;
use GraphQL\Middleware\Factory\GeneratedSchemaFactory;
use GraphQL\Middleware\Generator\ResolverGenerator;
use GraphQL\Middleware\Generator\SimpleTemplateEngine;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use GraphQL\Middleware\Config\GeneratorConfig;
use GraphQL\Middleware\Generator\AstSchemaAnalyzer;
use GraphQL\Middleware\Generator\DefaultTypeMapper;

class ResolverGeneratorTest extends TestCase
{
    private const TEST_SCHEMA = <<<'GRAPHQL'
type Query {
    user(id: ID!): User
}

type Mutation {
    createUser(name: String!, age: Int): User
}

"""
A user in the system
"""
type User {
    id: ID!
    name: String!
    age: Int
    posts: [Post!]
}

type Post {
    id: ID!
    title: String!
    content: String
}
GRAPHQL;

    private \org\bovigo\vfs\vfsStreamDirectory $root;
    private SchemaConfig&MockObject $config;
    private GeneratedSchemaFactory $schemaFactory;
    private ResolverGenerator $generator;
    private GeneratorConfig $generatorConfig;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('test', 0777);
        vfsStream::create([
            'src' => [
                'Query' => [],
                'User' => [],
                'Post' => []
            ]
        ], $this->root);

        // Setup schema directory and file
        $schemaDir = vfsStream::newDirectory('schema')->at($this->root);
        $schemaPath = $schemaDir->url() . '/schema.graphql';
        file_put_contents($schemaPath, self::TEST_SCHEMA);

        // Create cache directory
        $cacheDir = vfsStream::newDirectory('cache')->at($this->root);

        // Set up schema configuration
        $this->config = $this->createMock(SchemaConfig::class);
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

        // Create schema factory
        $this->schemaFactory = new GeneratedSchemaFactory($this->config);

        // Create generator config
        $this->generatorConfig = new GeneratorConfig([
            'entityConfig' => [
                'namespace' => 'App\\GraphQL\\Entity',
                'fileLocation' => $this->root->url() . '/src/Entity',
                'templatePath' => dirname(__DIR__, 2) . '/templates/entity.php.template',
            ],
            'requestConfig' => [
                'namespace' => 'App\\GraphQL\\Request',
                'fileLocation' => $this->root->url() . '/src/Request',
                'templatePath' => dirname(__DIR__, 2) . '/templates/request.php.template',
            ],
            'resolverConfig' => [
                'namespace' => 'App\\GraphQL\\Resolver',
                'fileLocation' => $this->root->getChild('src')->url(),
                'templatePath' => dirname(__DIR__, 2) . '/templates/resolver.php.template',
            ],
            'typeMappings' => [
                'ID' => 'string',
                'String' => 'string',
                'Int' => 'int',
                'Float' => 'float',
                'Boolean' => 'bool',
            ],
            'customTypes' => [],
            'isImmutable' => true,
            'hasStrictTypes' => true,
        ]);

        // Create schema analyzer
        $schema = \GraphQL\Utils\BuildSchema::build(self::TEST_SCHEMA);
        $analyzer = new AstSchemaAnalyzer($schema, new DefaultTypeMapper());

        // Setup resolver generator
        $this->generator = new ResolverGenerator(
            $analyzer,
            $this->generatorConfig,
            new SimpleTemplateEngine(),
        );
    }

    public function testGeneratesResolvers(): void
    {
        $this->generator->generateAll();

        $expectedFiles = [
            'Query/UserResolver.php',
            'Mutation/CreateUserResolver.php',
            'User/PostsResolver.php',
        ];

        foreach ($expectedFiles as $file) {
            $this->assertFileExists($this->root->getChild('src')->url() . '/' . $file);
        }
    }

    public function testGeneratedResolverContent(): void
    {
        $this->generator->generateAll();

        // Test Query resolver
        $userResolver = file_get_contents(
            $this->root->getChild('src')->url() . '/Query/UserResolver.php'
        );

        if ($userResolver === false) {
            $this->fail('Failed to read user resolver file');
        }

        $this->assertStringContainsString('namespace App\GraphQL\Resolver\Query', $userResolver);
        $this->assertStringContainsString('class UserResolver', $userResolver);
        $this->assertStringContainsString(
            'public function __invoke(mixed $objectValue, array $args, mixed $context): User|null',
            $userResolver,
        );
        $this->assertStringContainsString('@param array $args', $userResolver);
        $this->assertStringContainsString('- id: string', $userResolver);

        // Test Mutation resolver
        $createUserResolver = file_get_contents(
            $this->root->getChild('src')->url() . '/Mutation/CreateUserResolver.php'
        );

        if ($createUserResolver === false) {
            $this->fail('Failed to read create user resolver file');
        }

        $this->assertStringContainsString('namespace App\GraphQL\Resolver\Mutation', $createUserResolver);
        $this->assertStringContainsString('class CreateUserResolver', $createUserResolver);
        $this->assertStringContainsString(
            'public function __invoke(mixed $objectValue, array $args, mixed $context): User|null',
            $createUserResolver,
        );
        $this->assertStringContainsString('@param array $args', $createUserResolver);
        $this->assertStringContainsString('- name: string', $createUserResolver);
        $this->assertStringContainsString('- age: int|null', $createUserResolver);
    }

    public function testSkipsExistingResolvers(): void
    {
        $existingContent = '<?php /* Existing resolver */';
        $resolverPath = $this->root->getChild('src')->url() . '/Query/UserResolver.php';
        file_put_contents($resolverPath, $existingContent);

        $this->generator->generateAll();

        $this->assertFileExists($resolverPath);
        $this->assertEquals($existingContent, file_get_contents($resolverPath));
    }

    public function testHandlesSchemaWithOnlyTypes(): void
    {
        $typesOnlySchema = <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Post {
            id: ID!
            title: String!
        }
        GRAPHQL;

        // Create schema analyzer with types-only schema
        $schema = \GraphQL\Utils\BuildSchema::build($typesOnlySchema);
        $analyzer = new AstSchemaAnalyzer($schema, new DefaultTypeMapper());

        $generator = new ResolverGenerator(
            $analyzer,
            $this->generatorConfig,
            new SimpleTemplateEngine(),
        );

        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage('No resolver requirements found in schema');

        $generator->generateAll();
    }

    public function testThrowsExceptionOnMissingTemplate(): void
    {
        // Create config with non-existent template
        $config = new GeneratorConfig([
            'entityConfig' => [
                'namespace' => 'App\\GraphQL\\Entity',
                'fileLocation' => $this->root->url() . '/src/Entity',
                'templatePath' => dirname(__DIR__, 2) . '/templates/entity.php.template',
            ],
            'requestConfig' => [
                'namespace' => 'App\\GraphQL\\Request',
                'fileLocation' => $this->root->url() . '/src/Request',
                'templatePath' => dirname(__DIR__, 2) . '/templates/request.php.template',
            ],
            'resolverConfig' => [
                'namespace' => 'App\\GraphQL\\Resolver',
                'fileLocation' => $this->root->getChild('src')->url(),
                'templatePath' => '/non/existent/template.php',
            ],
            'typeMappings' => [],
            'customTypes' => [],
            'isImmutable' => true,
            'hasStrictTypes' => true,
        ]);

        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage('Template file not found');

        $schema = \GraphQL\Utils\BuildSchema::build(self::TEST_SCHEMA);
        new ResolverGenerator(
            new AstSchemaAnalyzer($schema, new DefaultTypeMapper()),
            $config,
            new SimpleTemplateEngine(),
        );
    }
}
