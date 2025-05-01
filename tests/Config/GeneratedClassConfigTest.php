<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Config;

use GraphQL\Middleware\Config\EntityConfig;
use PHPUnit\Framework\TestCase;

class GeneratedClassConfigTest extends TestCase
{
    private EntityConfig $config;

    protected function setUp(): void
    {
        $this->config = new EntityConfig([
            'namespace' => 'App\\Entity',
            'fileLocation' => '/path/to/entities',
            'templatePath' => '/path/to/template.php',
        ]);
    }

    public function testGetters(): void
    {
        $this->assertEquals('App\\Entity', $this->config->getNamespace());
        $this->assertEquals('/path/to/entities', $this->config->getFileLocation());
        $this->assertEquals('/path/to/template.php', $this->config->getTemplatePath());
    }

    public function testToArray(): void
    {
        $data = [
            'namespace' => 'App\\Entity',
            'fileLocation' => '/path/to/entities',
            'templatePath' => '/path/to/template.php',
        ];

        $this->assertEquals($data, $this->config->toArray());
    }
}
