<?php

namespace GraphQL\Middleware\Test\Composer;

use PHPUnit\Framework\TestCase;
use GraphQL\Middleware\Composer\Plugin;
use Composer\Script\Event;
use Composer\IO\NullIO;

class PluginTest extends TestCase
{
    private $tempDir;
    private $composerJsonPath;
    private $originalComposerJson;
    private $oldCwd;

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
        $this->oldCwd = getcwd();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->oldCwd);
        @unlink($this->composerJsonPath);
        @rmdir($this->tempDir);
    }

    public function testAddGenerateResolversScript()
    {
        $plugin = new Plugin();
        $event = $this->createMock(Event::class);
        $event->method('getIO')->willReturn(new NullIO());

        $plugin->addGenerateResolversScript($event);

        $composerData = json_decode(file_get_contents($this->composerJsonPath), true);
        $this->assertArrayHasKey('generate-resolvers', $composerData['scripts']);
        $this->assertEquals('vendor/bin/generate-resolvers', $composerData['scripts']['generate-resolvers']);
    }

    public function testDoesNotOverwriteExistingScript()
    {
        $composerData = $this->originalComposerJson;
        $composerData['scripts']['generate-resolvers'] = 'custom/script';
        file_put_contents($this->composerJsonPath, json_encode($composerData));

        $plugin = new Plugin();
        $event = $this->createMock(Event::class);
        $event->method('getIO')->willReturn(new NullIO());

        $plugin->addGenerateResolversScript($event);

        $composerData = json_decode(file_get_contents($this->composerJsonPath), true);
        $this->assertEquals('custom/script', $composerData['scripts']['generate-resolvers']);
    }
}