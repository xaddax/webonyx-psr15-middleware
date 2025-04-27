<?php

namespace GraphQL\Middleware\Test\Composer;

use PHPUnit\Framework\TestCase;
use GraphQL\Middleware\Composer\Plugin;
use Composer\Script\Event;
use Composer\IO\NullIO;

class PluginTest extends TestCase
{
    private string $tempDir;
    private string $composerJsonPath;
    private array $originalComposerJson;
    private string $oldCwd;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/composer_plugin_test_' . uniqid();
        mkdir($this->tempDir);
        $this->composerJsonPath = $this->tempDir . '/composer.json';
        $this->originalComposerJson = [
            "name" => "test/project",
            "scripts" => []
        ];
        file_put_contents($this->composerJsonPath, json_encode($this->originalComposerJson));

        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Failed to get current working directory');
        }
        $this->oldCwd = $cwd;
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->oldCwd);
        @unlink($this->composerJsonPath);
        @rmdir($this->tempDir);
    }

    public function testAddGenerateResolversScript(): void
    {
        $plugin = new Plugin();
        $event = $this->createMock(Event::class);
        $event->method('getIO')->willReturn(new NullIO());

        $plugin->addGenerateResolversScript($event);

        $composerJsonContent = file_get_contents($this->composerJsonPath);
        if ($composerJsonContent === false) {
            $this->fail('Failed to read composer.json');
        }

        $composerData = json_decode($composerJsonContent, true);
        if (!is_array($composerData)) {
            $this->fail('Failed to decode composer.json');
        }

        $this->assertArrayHasKey('scripts', $composerData);
        $this->assertArrayHasKey('generate-resolvers', $composerData['scripts']);
        $this->assertEquals('vendor/bin/generate-resolvers', $composerData['scripts']['generate-resolvers']);
    }

    public function testDoesNotOverwriteExistingScript(): void
    {
        $composerData = $this->originalComposerJson;
        $composerData['scripts']['generate-resolvers'] = 'custom/script';
        file_put_contents($this->composerJsonPath, json_encode($composerData));

        $plugin = new Plugin();
        $event = $this->createMock(Event::class);
        $event->method('getIO')->willReturn(new NullIO());

        $plugin->addGenerateResolversScript($event);

        $composerJsonContent = file_get_contents($this->composerJsonPath);
        if ($composerJsonContent === false) {
            $this->fail('Failed to read composer.json');
        }

        $composerData = json_decode($composerJsonContent, true);
        if (!is_array($composerData)) {
            $this->fail('Failed to decode composer.json');
        }

        $this->assertArrayHasKey('scripts', $composerData);
        $this->assertArrayHasKey('generate-resolvers', $composerData['scripts']);
        $this->assertEquals('custom/script', $composerData['scripts']['generate-resolvers']);
    }
}
