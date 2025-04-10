<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Generator;

use GraphQL\Middleware\Generator\SimpleTemplateEngine;
use PHPUnit\Framework\TestCase;

class SimpleTemplateEngineTest extends TestCase
{
    private SimpleTemplateEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SimpleTemplateEngine();
    }

    public function testRendersTemplate(): void
    {
        $template = 'Hello {{name}}!';
        $variables = ['name' => 'World'];

        $result = $this->engine->render($template, $variables);

        $this->assertEquals('Hello World!', $result);
    }

    public function testSupportsTemplateExtension(): void
    {
        $this->assertTrue($this->engine->supports('test.template'));
        $this->assertFalse($this->engine->supports('test.php'));
    }

    public function testKeepsUnknownVariables(): void
    {
        $template = 'Hello {{unknown}}!';
        $variables = ['name' => 'World'];

        $result = $this->engine->render($template, $variables);

        $this->assertEquals('Hello {{unknown}}!', $result);
    }
}
