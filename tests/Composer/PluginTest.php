<?php

namespace GraphQL\Middleware\Test\Composer;

use PHPUnit\Framework\TestCase;
use GraphQL\Middleware\Composer\Plugin;
use Composer\Script\Event;
use Composer\IO\NullIO;
use Composer\Composer;
use Composer\IO\IOInterface;

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

    public function testAddGeneratorScripts(): void
    {
        $plugin = new Plugin();
        $event = $this->createMock(Event::class);
        $event->method('getIO')->willReturn(new NullIO());

        $plugin->addGeneratorScripts($event);

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
        $this->assertArrayHasKey('generate-requests', $composerData['scripts']);
        $this->assertEquals('vendor/bin/generate-requests', $composerData['scripts']['generate-requests']);
    }

    public function testDoesNotOverwriteExistingScripts(): void
    {
        $composerData = $this->originalComposerJson;
        $composerData['scripts']['generate-resolvers'] = 'custom/resolver-script';
        $composerData['scripts']['generate-requests'] = 'custom/request-script';
        file_put_contents($this->composerJsonPath, json_encode($composerData));

        $plugin = new Plugin();
        $event = $this->createMock(Event::class);
        $event->method('getIO')->willReturn(new NullIO());

        $plugin->addGeneratorScripts($event);

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
        $this->assertEquals('custom/resolver-script', $composerData['scripts']['generate-resolvers']);
        $this->assertArrayHasKey('generate-requests', $composerData['scripts']);
        $this->assertEquals('custom/request-script', $composerData['scripts']['generate-requests']);
    }

    public function testActivate(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $io = $this->createMock(IOInterface::class);

        // The method is empty, but we test that it doesn't throw any exceptions
        $plugin->activate($composer, $io);
        $this->assertTrue(true); // Assert that we reached this point without exceptions
    }

    public function testDeactivate(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $io = $this->createMock(IOInterface::class);

        // The method is empty, but we test that it doesn't throw any exceptions
        $plugin->deactivate($composer, $io);
        $this->assertTrue(true); // Assert that we reached this point without exceptions
    }

    public function testUninstall(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $io = $this->createMock(IOInterface::class);

        // The method is empty, but we test that it doesn't throw any exceptions
        $plugin->uninstall($composer, $io);
        $this->assertTrue(true); // Assert that we reached this point without exceptions
    }
}
