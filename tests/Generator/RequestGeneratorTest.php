<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Generator;

use GraphQL\Middleware\Generator\RequestGenerator;
use GraphQL\Middleware\Generator\AstSchemaAnalyzer;
use GraphQL\Middleware\Contract\TemplateEngineInterface;
use GraphQL\Middleware\Config\GeneratorConfig;
use GraphQL\Middleware\Config\RequestConfig;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\MockObject\MockObject;

class RequestGeneratorTest extends TestCase
{
    private const TEST_SCHEMA = <<<'GRAPHQL'
input UserInput {
    name: String!
    age: Int
    email: String!
}

input AddressInput {
    street: String!
    city: String!
    country: String!
}
GRAPHQL;

    private RequestGenerator $generator;
    private \org\bovigo\vfs\vfsStreamDirectory $root;
    private TemplateEngineInterface&MockObject $templateEngine;
    private AstSchemaAnalyzer&MockObject $schemaAnalyzer;
    private GeneratorConfig&MockObject $config;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('test');
        // Set up template directory and file
        $templateDir = vfsStream::newDirectory('templates')->at($this->root);
        $templatePath = $templateDir->url() . '/request.php.template';
        file_put_contents($templatePath, file_get_contents(__DIR__ . '/../../templates/request.php.template'));

        // Set up output directory
        vfsStream::newDirectory('src/Request', 0777)->at($this->root);

        // Mock template engine
        $this->templateEngine = $this->createMock(TemplateEngineInterface::class);

        // Mock schema analyzer
        $this->schemaAnalyzer = $this->createMock(AstSchemaAnalyzer::class);

        // Set up configuration
        $requestConfig = new RequestConfig([
            'namespace' => 'App\\Request',
            'fileLocation' => 'vfs://test/src/Request',
            'templatePath' => $templatePath,
        ]);

        $this->config = $this->createMock(GeneratorConfig::class);
        $this->config->method('getRequestConfig')
            ->willReturn($requestConfig);

        $this->generator = new RequestGenerator(
            $this->schemaAnalyzer,
            $this->config,
            $this->templateEngine
        );
    }

    public function testGenerateAll(): void
    {
        $requirements = [
            'UserInput' => [
                'name' => 'UserInput',
                'description' => null,
                'fields' => [
                    'name' => 'string',
                    'age' => 'int|null',
                    'email' => 'string',
                ],
            ],
            'AddressInput' => [
                'name' => 'AddressInput',
                'description' => null,
                'fields' => [
                    'street' => 'string',
                    'city' => 'string',
                    'country' => 'string',
                ],
            ],
        ];

        $this->schemaAnalyzer->expects($this->once())
            ->method('getRequestRequirements')
            ->willReturn($requirements);

        $this->templateEngine->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template, $data) {
                return "<?php\n// Generated class for {$data['className']}\nclass {$data['className']} {}\n";
            });

        $this->generator->generateAll();

        $this->assertFileExists('vfs://test/src/Request/UserInput.php');
        $this->assertFileExists('vfs://test/src/Request/AddressInput.php');
    }

    public function testGenerateAllWithEmptyRequirements(): void
    {
        $this->schemaAnalyzer->expects($this->once())
            ->method('getRequestRequirements')
            ->willReturn([]);

        $this->expectException(\GraphQL\Middleware\Exception\GeneratorException::class);
        $this->expectExceptionMessage('No request requirements found in schema');

        $this->generator->generateAll();
    }

    public function testSkipsExistingFiles(): void
    {
        $requirements = [
            'UserInput' => [
                'name' => 'UserInput',
                'description' => null,
                'fields' => [
                    'name' => 'string',
                ],
            ],
        ];

        $this->schemaAnalyzer->expects($this->once())
            ->method('getRequestRequirements')
            ->willReturn($requirements);

        // Create the file before generation
        $filePath = 'vfs://test/src/Request/UserInput.php';
        file_put_contents($filePath, '<?php // Existing file');

        $this->templateEngine->expects($this->never())
            ->method('render');

        $this->generator->generateAll();

        $this->assertFileExists($filePath);
        $this->assertEquals('<?php // Existing file', file_get_contents($filePath));
    }

    public function testThrowsExceptionWhenTemplateNotFound(): void
    {
        $requestConfig = new RequestConfig([
            'namespace' => 'App\\Request',
            'fileLocation' => 'vfs://test/src/Request',
            'templatePath' => 'vfs://test/templates/nonexistent.php.template',
        ]);

        $this->config = $this->createMock(GeneratorConfig::class);
        $this->config->method('getRequestConfig')
            ->willReturn($requestConfig);

        $this->expectException(\GraphQL\Middleware\Exception\GeneratorException::class);
        $this->expectExceptionMessage('Template file not found: vfs://test/templates/nonexistent.php.template');

        new RequestGenerator(
            $this->schemaAnalyzer,
            $this->config,
            $this->templateEngine
        );
    }

    public function testThrowsExceptionWhenTemplateReadFails(): void
    {
        // Create a new root
        $this->root = vfsStream::setup('test');
        
        // Create templates directory and unreadable file
        $templateDir = vfsStream::newDirectory('templates', 0777)->at($this->root);
        $templateFile = vfsStream::newFile('unreadable.php.template', 0000)
            ->at($templateDir)
            ->chown(vfsStream::OWNER_ROOT)
            ->chgrp(vfsStream::GROUP_ROOT);
        
        // Create src directory for requests
        vfsStream::newDirectory('src/Request', 0777)->at($this->root);

        $requestConfig = new RequestConfig([
            'namespace' => 'App\\Request',
            'fileLocation' => vfsStream::url('test/src/Request'),
            'templatePath' => $templateFile->url(),
        ]);

        $this->config = $this->createMock(GeneratorConfig::class);
        $this->config->method('getRequestConfig')
            ->willReturn($requestConfig);

        $generator = new RequestGenerator(
            $this->schemaAnalyzer,
            $this->config,
            $this->templateEngine
        );

        $this->schemaAnalyzer->expects($this->once())
            ->method('getRequestRequirements')
            ->willReturn([
                'UserInput' => [
                    'name' => 'UserInput',
                    'description' => null,
                    'fields' => ['name' => 'string'],
                ],
            ]);

        $this->expectException(\GraphQL\Middleware\Exception\GeneratorException::class);
        $this->expectExceptionMessage('Failed to read template file: ' . $templateFile->url());

        $generator->generateAll();
    }

    public function testThrowsExceptionWhenDirectoryCreationFails(): void
    {
        // Create a new root
        $this->root = vfsStream::setup('test');
        
        // Create templates directory with readable template
        $templateDir = vfsStream::newDirectory('templates', 0777)->at($this->root);
        $templateFile = vfsStream::newFile('request.php.template', 0777)
            ->at($templateDir)
            ->setContent(file_get_contents(__DIR__ . '/../../templates/request.php.template'));
        
        // Create src directory as a file to prevent subdirectory creation
        $srcDir = vfsStream::newDirectory('src', 0777)->at($this->root);
        vfsStream::newFile('Request', 0444)
            ->at($srcDir)
            ->chown(vfsStream::OWNER_ROOT)
            ->chgrp(vfsStream::GROUP_ROOT);

        $requestConfig = new RequestConfig([
            'namespace' => 'App\\Request',
            'fileLocation' => $srcDir->url() . '/Request',
            'templatePath' => $templateFile->url(),
        ]);

        $this->config = $this->createMock(GeneratorConfig::class);
        $this->config->method('getRequestConfig')
            ->willReturn($requestConfig);

        $generator = new RequestGenerator(
            $this->schemaAnalyzer,
            $this->config,
            $this->templateEngine
        );

        $requirements = [
            'UserInput' => [
                'name' => 'UserInput',
                'description' => null,
                'fields' => [
                    'name' => 'string',
                ],
            ],
        ];

        $this->schemaAnalyzer->expects($this->once())
            ->method('getRequestRequirements')
            ->willReturn($requirements);

        $this->templateEngine->expects($this->once())
            ->method('render')
            ->willReturn('<?php // Generated code');

        $this->expectException(\GraphQL\Middleware\Exception\GeneratorException::class);
        $this->expectExceptionMessage('Failed to create directory: ' . $srcDir->url() . '/Request');

        $generator->generateAll();
    }

    public function testThrowsExceptionWhenFileWriteFails(): void
    {
        // Create an unwritable directory
        vfsStream::newDirectory('src/Request', 0444)->at($this->root);

        $requirements = [
            'UserInput' => [
                'name' => 'UserInput',
                'description' => null,
                'fields' => [
                    'name' => 'string',
                ],
            ],
        ];

        $this->schemaAnalyzer->expects($this->once())
            ->method('getRequestRequirements')
            ->willReturn($requirements);

        $this->templateEngine->expects($this->once())
            ->method('render')
            ->willReturn('<?php // Generated code');

        $this->expectException(\GraphQL\Middleware\Exception\GeneratorException::class);
        $this->expectExceptionMessage('Failed to write request file: vfs://test/src/Request/UserInput.php');

        $this->generator->generateAll();
    }
}
