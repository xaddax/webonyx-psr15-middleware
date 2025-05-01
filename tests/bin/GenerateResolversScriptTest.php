<?php

namespace GraphQL\Middleware\Tests\Bin;

use PHPUnit\Framework\TestCase;

class GenerateResolversScriptTest extends TestCase
{
    private string $tempProjectDir;
    private ?string $containerPath = null;
    private string $oldCwd;
    private string $scriptPath;

    protected function setUp(): void
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Failed to get current working directory');
        }
        $this->oldCwd = $cwd;

        // Create a temp project directory
        $this->tempProjectDir = sys_get_temp_dir() . '/generate_resolvers_test_' . uniqid();
        mkdir($this->tempProjectDir);

        // Copy the vendor, tests, and src directories
        $this->recursiveCopy(__DIR__ . '/../../vendor', $this->tempProjectDir . '/vendor');
        $this->recursiveCopy(__DIR__ . '/../../tests', $this->tempProjectDir . '/tests');
        $this->recursiveCopy(__DIR__ . '/../../src', $this->tempProjectDir . '/src');

        // Copy the script to the project root
        $this->scriptPath = $this->tempProjectDir . '/generate-resolvers';
        $scriptContent = file_get_contents(__DIR__ . '/../../bin/generate-resolvers');
        if ($scriptContent === false) {
            throw new \RuntimeException('Failed to read generate-resolvers script');
        }
        if (file_put_contents($this->scriptPath, $scriptContent) === false) {
            throw new \RuntimeException('Failed to write generate-resolvers script');
        }
        chmod($this->scriptPath, 0755);
    }

    private function recursiveCopy(string $source, string $dest): void
    {
        if (!is_dir($source)) {
            throw new \RuntimeException("Source directory does not exist: $source");
        }

        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $dir = opendir($source);
        if ($dir === false) {
            throw new \RuntimeException("Failed to open source directory: $source");
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $file;
            $destPath = $dest . '/' . $file;

            if (is_dir($sourcePath)) {
                $this->recursiveCopy($sourcePath, $destPath);
            } else {
                if (!copy($sourcePath, $destPath)) {
                    throw new \RuntimeException("Failed to copy file: $sourcePath to $destPath");
                }
            }
        }

        closedir($dir);
    }

    protected function tearDown(): void
    {
        chdir($this->oldCwd);
        if ($this->containerPath !== null) {
            @unlink($this->containerPath);
        }
        $this->recursiveRemoveDir($this->tempProjectDir);
    }

    private function recursiveRemoveDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        if ($files === false) {
            return;
        }
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createContainerFile(string $content): void
    {
        $this->containerPath = $this->tempProjectDir . '/container.php';
        if (file_put_contents($this->containerPath, $content) === false) {
            throw new \RuntimeException('Failed to write container.php');
        }
    }

    private function runScript(): array
    {
        chdir($this->tempProjectDir);
        $output = shell_exec('php ' . escapeshellarg($this->scriptPath) . ' 2>&1');
        if ($output === null || $output === false) {
            throw new \RuntimeException('Failed to execute script');
        }
        $exitCode = str_starts_with($output, 'Error:') ? 1 : 0;
        return [$output, $exitCode];
    }

    public function testScriptSucceedsWithContainer(): void
    {
        $containerContent = <<<PHP
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Psr\Container\ContainerInterface;

return new class implements ContainerInterface {
    public function get(string \$id) {
        if (\$id === \GraphQL\Middleware\Generator\ResolverGenerator::class) {
            return new class extends \GraphQL\Middleware\Generator\ResolverGenerator {
                public function __construct() {}
                public function generateAll(): void {
                    // Do nothing, just simulate success
                }
            };
        }
        throw new \Exception("Not found");
    }
    public function has(string \$id): bool {
        return \$id === \GraphQL\Middleware\Generator\ResolverGenerator::class;
    }
};
PHP;
        $this->createContainerFile($containerContent);

        [$output, $exitCode] = $this->runScript();
        $this->assertStringContainsString('Resolvers generated successfully', $output);
        $this->assertSame(0, $exitCode);
    }

    public function testScriptFailsWithoutContainer(): void
    {
        [$output, $exitCode] = $this->runScript();
        $this->assertStringContainsString('No PSR-11 container found', $output);
        $this->assertSame(1, $exitCode);
    }

    public function testScriptFailsWithoutResolverGenerator(): void
    {
        $containerContent = <<<PHP
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Psr\Container\ContainerInterface;

return new class implements ContainerInterface {
    public function get(string \$id) {
        throw new \Exception("Not found");
    }
    public function has(string \$id): bool {
        return false;
    }
};
PHP;
        $this->createContainerFile($containerContent);

        [$output, $exitCode] = $this->runScript();
        $this->assertStringContainsString('ResolverGenerator service not found', $output);
        $this->assertSame(1, $exitCode);
    }

    public function testScriptFailsWhenGeneratorThrowsException(): void
    {
        $containerContent = <<<PHP
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Psr\Container\ContainerInterface;

return new class implements ContainerInterface {
    public function get(string \$id) {
        if (\$id === \GraphQL\Middleware\Generator\ResolverGenerator::class) {
            return new class extends \GraphQL\Middleware\Generator\ResolverGenerator {
                public function __construct() {}
                public function generateAll(): void {
                    throw new \RuntimeException('Test error');
                }
            };
        }
        throw new \Exception("Not found");
    }
    public function has(string \$id): bool {
        return \$id === \GraphQL\Middleware\Generator\ResolverGenerator::class;
    }
};
PHP;
        $this->createContainerFile($containerContent);

        [$output, $exitCode] = $this->runScript();
        $this->assertStringContainsString('Test error', $output);
        $this->assertSame(1, $exitCode);
    }
}
