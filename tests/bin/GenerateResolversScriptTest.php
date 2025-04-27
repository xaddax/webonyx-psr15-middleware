<?php

namespace GraphQL\Middleware\Test\Bin;

use PHPUnit\Framework\TestCase;

class GenerateResolversScriptTest extends TestCase
{
    private string $tempProjectDir;
    private string $containerPath;
    private string $scriptPath;
    private string $oldCwd;

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

        // Copy and patch the bin script into the temp project
        $originalScriptPath = realpath(__DIR__ . '/../../bin/generate-resolvers');
        if ($originalScriptPath === false) {
            throw new \RuntimeException('Failed to get realpath of original script');
        }

        $originalScript = file_get_contents($originalScriptPath);
        if ($originalScript === false) {
            throw new \RuntimeException('Failed to read original script');
        }

        $patchedScript = preg_replace(
            '/require\s+__DIR__\s*\.\s*[\'"]\/\.\.\/vendor\/autoload\.php[\'"]\s*;/',
            'require __DIR__ . "/vendor/autoload.php";',
            $originalScript
        );
        if ($patchedScript === null) {
            throw new \RuntimeException('Failed to patch script (first replacement)');
        }

        $patchedScript = preg_replace(
            '/\$containerFiles\s*=\s*\[\s*__DIR__\s*\.\s*[\'"]\/\.\.\/container\.php[\'"],\s*' .
            '__DIR__\s*\.\s*[\'"]\/\.\.\/config\/container\.php[\'"],\s*\];/',
            '$containerFiles = [__DIR__ . "/container.php", __DIR__ . "/config/container.php"];',
            $patchedScript
        );
        if ($patchedScript === null) {
            throw new \RuntimeException('Failed to patch script (second replacement)');
        }

        $this->scriptPath = $this->tempProjectDir . '/generate-resolvers';
        if (file_put_contents($this->scriptPath, $patchedScript) === false) {
            throw new \RuntimeException('Failed to write patched script');
        }
        chmod($this->scriptPath, 0755);

        // Symlink the real vendor/autoload.php into the temp project
        $realAutoload = realpath(__DIR__ . '/../../vendor/autoload.php');
        if ($realAutoload === false) {
            throw new \RuntimeException('Failed to get realpath of autoload.php');
        }

        $tempVendorDir = $this->tempProjectDir . '/vendor';
        mkdir($tempVendorDir);
        if (!symlink($realAutoload, $tempVendorDir . '/autoload.php')) {
            throw new \RuntimeException('Failed to create symlink for autoload.php');
        }

        // Write the container.php in the temp project root
        $this->containerPath = $this->tempProjectDir . '/container.php';
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
        if (file_put_contents($this->containerPath, $containerContent) === false) {
            throw new \RuntimeException('Failed to write container.php');
        }
    }

    protected function tearDown(): void
    {
        chdir($this->oldCwd);
        @unlink($this->containerPath);
        @unlink($this->scriptPath);
        @unlink($this->tempProjectDir . '/vendor/autoload.php');
        @rmdir($this->tempProjectDir . '/vendor');
        @rmdir($this->tempProjectDir);
    }

    public function testScriptSucceedsWithContainer(): void
    {
        $output = [];
        $returnVar = null;
        $cmd = sprintf(
            'php %s 2>&1',
            escapeshellarg('./generate-resolvers')
        );
        // Run from temp project root so script finds container.php and vendor/autoload.php
        chdir($this->tempProjectDir);
        exec($cmd, $output, $returnVar);
        chdir($this->oldCwd);

        $outputText = implode("\n", $output);
        $this->assertStringContainsString('Resolvers generated successfully', $outputText);
        $this->assertSame(0, $returnVar);
    }
}
