<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Generator;

use GraphQL\Middleware\Contract\TemplateEngineInterface;
use GraphQL\Middleware\Contract\TypeMapperInterface;
use GraphQL\Middleware\Factory\GeneratedSchemaFactory;
use GraphQL\Middleware\Generator\AstSchemaAnalyzer;
use GraphQL\Middleware\Generator\EntityGenerator;
use GraphQL\Middleware\Config\GeneratorConfig;
use GraphQL\Middleware\Config\EntityConfig;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\MockObject\MockObject;
use GraphQL\Language\Parser;
use GraphQL\Utils\BuildSchema;

class EntityGeneratorTest extends TestCase
{
    private const TEST_SCHEMA = <<<'GRAPHQL'
type User {
    id: ID!
    name: String!
    age: Int
    email: String!
}

type Post {
    id: ID!
    title: String!
    content: String!
    author: User!
}
GRAPHQL;

    private EntityGenerator $generator;
    private \org\bovigo\vfs\vfsStreamDirectory $root;
    private TemplateEngineInterface&MockObject $templateEngine;
    private AstSchemaAnalyzer&MockObject $schemaAnalyzer;
    private GeneratorConfig&MockObject $config;
    private GeneratedSchemaFactory&MockObject $schemaFactory;
    private TypeMapperInterface&MockObject $typeMapper;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('test');

        // Set up template directory and file in vfs
        $templateDir = vfsStream::newDirectory('templates')->at($this->root);
        $templatePath = $templateDir->url() . '/entity.php.template';
        $templateContent = "<?php\n// Dummy entity template\n";
        file_put_contents($templatePath, $templateContent);

        // Set up output directory
        vfsStream::newDirectory('src/Entity', 0777)->at($this->root);

        // Mock template engine
        $this->templateEngine = $this->createMock(TemplateEngineInterface::class);

        // Mock schema analyzer
        $this->schemaAnalyzer = $this->createMock(AstSchemaAnalyzer::class);

        // Mock schema factory and type mapper
        $this->schemaFactory = $this->createMock(GeneratedSchemaFactory::class);
        $this->typeMapper = $this->createMock(TypeMapperInterface::class);

        // Set up configuration
        $entityConfig = new EntityConfig([
            'namespace' => 'App\\Entity',
            'fileLocation' => 'vfs://test/src/Entity',
            'templatePath' => $templatePath,
        ]);

        $this->config = $this->createMock(GeneratorConfig::class);
        $this->config->method('getEntityConfig')
            ->willReturn($entityConfig);

        $this->generator = new EntityGenerator(
            $this->schemaAnalyzer,
            $this->config,
            $this->templateEngine
        );
    }

    public function testGenerateAll(): void
    {
        $requirements = [
            'User' => [
                'name' => 'User',
                'description' => null,
                'fields' => [
                    'id' => 'string',
                    'name' => 'string',
                    'age' => 'int|null',
                    'email' => 'string',
                ],
            ],
            'Post' => [
                'name' => 'Post',
                'description' => null,
                'fields' => [
                    'id' => 'string',
                    'title' => 'string',
                    'content' => 'string',
                    'author' => 'User',
                ],
            ],
        ];

        $this->schemaAnalyzer->expects($this->once())
            ->method('getEntityRequirements')
            ->willReturn($requirements);

        $this->templateEngine->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template, $data) {
                return "<?php\n// Generated class for {$data['className']}\nclass {$data['className']} extends BaseEntity {}\n";
            });

        $this->generator->generateAll();

        $this->assertFileExists('vfs://test/src/Entity/User.php');
        $this->assertFileExists('vfs://test/src/Entity/Post.php');

        $content = file_get_contents('vfs://test/src/Entity/User.php');
        if ($content === false) {
            $this->fail('Failed to read generated file');
        }
        $this->assertStringContainsString('class User extends BaseEntity', $content);
    }

    public function testSchemaAnalysis(): void
    {
        // Return a real Schema object, not a DocumentNode
        $schema = \GraphQL\Utils\BuildSchema::build(self::TEST_SCHEMA);
        $this->schemaFactory->method('createSchema')
            ->willReturn($schema);
        // Mock typeMapper to return correct PHP types
        $this->typeMapper->method('toPhpType')
            ->willReturnCallback(function ($type) {
                switch ($type) {
                    case 'ID':
                    case 'String':
                        return 'string';
                    case 'Int':
                        return 'int';
                    default:
                        return 'string';
                }
            });

        $analyzer = new AstSchemaAnalyzer($this->schemaFactory, $this->typeMapper);
        $requirements = $analyzer->getEntityRequirements();

        $this->assertNotEmpty($requirements);
        $this->assertArrayHasKey('User', $requirements);
        $this->assertArrayHasKey('Post', $requirements);

        // Verify the schema matches our TEST_SCHEMA constant
        $this->assertEquals([
            'id' => 'string',
            'name' => 'string',
            'age' => 'int|null',
            'email' => 'string',
        ], $requirements['User']['fields']);
    }

    public function testGenerateAllWithEmptyRequirements(): void
    {
        $this->schemaAnalyzer->expects($this->once())
            ->method('getEntityRequirements')
            ->willReturn([]);

        $this->expectException(\GraphQL\Middleware\Exception\GeneratorException::class);
        $this->expectExceptionMessage('No entity requirements found in schema');

        $this->generator->generateAll();
    }

    public function testSkipsExistingFiles(): void
    {
        $requirements = [
            'User' => [
                'name' => 'User',
                'description' => null,
                'fields' => [
                    'name' => 'string',
                ],
            ],
        ];

        $this->schemaAnalyzer->expects($this->once())
            ->method('getEntityRequirements')
            ->willReturn($requirements);

        // Create the file before generation
        $filePath = 'vfs://test/src/Entity/User.php';
        file_put_contents($filePath, '<?php // Existing file');

        $this->templateEngine->expects($this->never())
            ->method('render');

        $this->generator->generateAll();

        $this->assertFileExists($filePath);
        $this->assertEquals('<?php // Existing file', file_get_contents($filePath));
    }

    public function testThrowsExceptionWhenTemplateNotFound(): void
    {
        $entityConfig = new EntityConfig([
            'namespace' => 'App\\Entity',
            'fileLocation' => 'vfs://test/src/Entity',
            'templatePath' => 'vfs://test/templates/nonexistent.php.template',
        ]);

        $this->config = $this->createMock(GeneratorConfig::class);
        $this->config->method('getEntityConfig')
            ->willReturn($entityConfig);

        $this->expectException(\GraphQL\Middleware\Exception\GeneratorException::class);
        $this->expectExceptionMessage('Template file not found: vfs://test/templates/nonexistent.php.template');

        new EntityGenerator(
            $this->schemaAnalyzer,
            $this->config,
            $this->templateEngine
        );
    }

    public function testThrowsExceptionWhenTemplateReadFails(): void
    {
        // Create an unreadable template file
        $path = 'vfs://test/templates/unreadable.php.template';
        $templateDir = $this->root->getChild('templates');
        if ($templateDir instanceof \org\bovigo\vfs\vfsStreamDirectory) {
            $file = vfsStream::newFile('unreadable.php.template', 0000)
                ->at($templateDir)
                ->chown(vfsStream::OWNER_ROOT)
                ->chgrp(vfsStream::GROUP_ROOT);
        }

        $entityConfig = new EntityConfig([
            'namespace' => 'App\\Entity',
            'fileLocation' => 'vfs://test/src/Entity',
            'templatePath' => $path,
        ]);

        $this->config = $this->createMock(GeneratorConfig::class);
        $this->config->method('getEntityConfig')
            ->willReturn($entityConfig);

        $generator = new EntityGenerator(
            $this->schemaAnalyzer,
            $this->config,
            $this->templateEngine
        );

        $this->schemaAnalyzer->expects($this->once())
            ->method('getEntityRequirements')
            ->willReturn([
                'User' => [
                    'name' => 'User',
                    'description' => null,
                    'fields' => ['name' => 'string'],
                ],
            ]);

        $this->expectException(\GraphQL\Middleware\Exception\GeneratorException::class);
        $message = sprintf('Failed to read template file: %s', $path);
        $this->expectExceptionMessage($message);

        $generator->generateAll();
    }

    public function testThrowsExceptionWhenDirectoryCreationFails(): void
    {
        // Create a new root with specific permissions
        $this->root = vfsStream::setup('test', 0444); // Read-only root

        // Set up template directory and file
        $templateDir = vfsStream::newDirectory('templates', 0777)->at($this->root);
        $templatePath = $templateDir->url() . '/entity.php.template';
        $templateContent = file_get_contents(__DIR__ . '/../../templates/entity.php.template');
        file_put_contents($templatePath, $templateContent);

        // Create src directory but make it read-only
        $srcDir = vfsStream::newDirectory('src', 0444)->at($this->root);
        $entityPath = $srcDir->url() . '/Entity';

        // Update config to use new paths
        $entityConfig = new EntityConfig([
            'namespace' => 'App\\Entity',
            'fileLocation' => $entityPath,
            'templatePath' => $templatePath,
        ]);

        $this->config = $this->createMock(GeneratorConfig::class);
        $this->config->method('getEntityConfig')
            ->willReturn($entityConfig);

        $generator = new EntityGenerator(
            $this->schemaAnalyzer,
            $this->config,
            $this->templateEngine
        );

        $requirements = [
            'User' => [
                'name' => 'User',
                'description' => null,
                'fields' => [
                    'name' => 'string',
                ],
            ],
        ];

        $this->schemaAnalyzer->expects($this->once())
            ->method('getEntityRequirements')
            ->willReturn($requirements);

        $this->templateEngine->expects($this->once())
            ->method('render')
            ->willReturn('<?php // Generated code');

        $this->expectException(\GraphQL\Middleware\Exception\GeneratorException::class);
        $this->expectExceptionMessage(
            'Failed to create directory: ' . $entityPath
        );

        $generator->generateAll();
    }
}
