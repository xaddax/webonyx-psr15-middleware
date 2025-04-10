<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Generator;

use GraphQL\Language\Parser;
use GraphQL\Middleware\Contract\SchemaConfigurationInterface;
use GraphQL\Middleware\Exception\GeneratorException;
use GraphQL\Middleware\Factory\GeneratedSchemaFactory;
use GraphQL\Middleware\Generator\ResolverGenerator;
use GraphQL\Middleware\Generator\SimpleTemplateEngine;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ResolverGeneratorTest extends TestCase
{
    private const TEST_SCHEMA = <<<'GRAPHQL'
type Query {
    user(id: ID!): User
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
    private SchemaConfigurationInterface&MockObject $config;
    private GeneratedSchemaFactory&MockObject $schemaFactory;
    private ResolverGenerator $generator;

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
        $this->config = $this->createMock(SchemaConfigurationInterface::class);

        // Setup schema factory with test schema
        $schemaDir = vfsStream::newDirectory('schema')->at($this->root);
        $schemaPath = $schemaDir->url() . '/schema.graphql';
        file_put_contents($schemaPath, self::TEST_SCHEMA);

        $cacheDir = vfsStream::newDirectory('cache')->at($this->root);

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

        // Setup schema factory with test schema
        $this->schemaFactory = $this->createMock(GeneratedSchemaFactory::class);
        $this->schemaFactory->expects($this->any())
            ->method('createSchema')
            ->willReturn(BuildSchema::build(self::TEST_SCHEMA));

        // Setup resolver generator
        $this->generator = new ResolverGenerator(
            $this->schemaFactory,
            $this->root->getChild('src')->url(),
            'App\GraphQL\Resolver',
            new SimpleTemplateEngine()
        );
    }

    public function testGeneratesResolvers(): void
    {
        $this->generator->generateAll();

        $expectedFiles = [
            'Query/UserResolver.php',
            'User/PostsResolver.php',
        ];

        foreach ($expectedFiles as $file) {
            $this->assertFileExists($this->root->getChild('src')->url() . '/' . $file);
        }
    }

    public function testGeneratedResolverContent(): void
    {
        $this->generator->generateAll();

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

    public function testThrowsExceptionOnSchemaCreationFailure(): void
    {
        $this->schemaFactory->method('createSchema')
            ->willThrowException(new \RuntimeException('Schema creation failed'));

        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage('Failed to create schema');

        $this->generator->generateAll();
    }


    public function testGeneratesResolverWithDescriptionAndArgs(): void
    {
        $schema = <<<'GRAPHQL'
        type Query {
            "Get user by ID"
            user(id: String!): User
        }

        type User {
            id: String!
        }
        GRAPHQL;

        $this->schemaFactory = $this->createMock(GeneratedSchemaFactory::class);
        $this->schemaFactory->expects($this->once())
            ->method('createSchema')
            ->willReturn(BuildSchema::build($schema));

        $this->generator = new ResolverGenerator(
            $this->schemaFactory,
            $this->root->getChild('src')->url(),
            'App\GraphQL\Resolver',
            new SimpleTemplateEngine()
        );

        $this->generator->generateAll();

        $resolverPath = $this->root->getChild('src')->url() . '/Query/UserResolver.php';
        $content = file_get_contents($resolverPath);
        $this->assertNotFalse($content, 'Failed to read resolver file');

        $this->assertStringContainsString('Get user by ID', $content);
        $this->assertStringContainsString('@param array $args', $content);
        $this->assertStringContainsString('- id: string', $content);
    }

    public function testUsesCustomTemplatePath(): void
    {
        $customTemplate = <<<'PHP'
<?php

declare(strict_types=1);

namespace {{namespace}};

/**
 * {{description}}
 */
class {{className}}
{
    public function __invoke(): {{returnType}}
    {
        // Custom template
        return null;
    }
}
PHP;

        $templatePath = $this->root->url() . '/custom-template.php';
        file_put_contents($templatePath, $customTemplate);

        $generator = new ResolverGenerator(
            $this->schemaFactory,
            $this->root->getChild('src')->url(),
            'App\GraphQL\Resolver',
            new SimpleTemplateEngine(),
            $templatePath
        );

        $generator->generateAll();

        $resolverPath = $this->root->getChild('src')->url() . '/Query/UserResolver.php';
        $content = file_get_contents($resolverPath);
        $this->assertNotFalse($content, 'Failed to read resolver file');

        $this->assertStringContainsString('// Custom template', $content);
        $this->assertStringContainsString('public function __invoke(): User|null', $content);
    }

    public function testThrowsExceptionOnMissingTemplate(): void
    {
        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage('Template file not found');

        new ResolverGenerator(
            $this->schemaFactory,
            $this->root->getChild('src')->url(),
            'App\GraphQL\Resolver',
            new SimpleTemplateEngine(),
            '/non/existent/template.php'
        );
    }
}
