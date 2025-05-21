<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Schema;

use GraphQL\Language\Source;
use GraphQL\Middleware\Schema\SchemaMerger;
use GraphQL\Middleware\Tests\TestCase;

class SchemaMergerTest extends TestCase
{
    private function createTempFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'gql');
        file_put_contents($file, $content);
        return $file;
    }

    private function removeTempFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function assertStringContainsIgnoringWhitespace(string $needle, string $haystack, string $message = ''): void
    {
        $normalize = fn($s) => preg_replace('/\s+/', '', $s);
        $this->assertStringContainsString($normalize($needle), $normalize($haystack), $message);
    }

    public function testMergesUniqueTypesAndOperations(): void
    {
        $file1 = $this->createTempFile('type Query { foo: String }');
        $file2 = $this->createTempFile('type Mutation { bar: Int }');
        $merger = new SchemaMerger();
        $source = $merger->merge([$file1, $file2]);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertStringContainsIgnoringWhitespace('type Query { foo: String }', $source->body);
        $this->assertStringContainsIgnoringWhitespace('type Mutation { bar: Int }', $source->body);
        $this->removeTempFiles([$file1, $file2]);
    }

    public function testAllowsDuplicateScalars(): void
    {
        $file1 = $this->createTempFile('scalar JSON');
        $file2 = $this->createTempFile('scalar JSON');
        $merger = new SchemaMerger();
        $source = $merger->merge([$file1, $file2]);
        $this->assertInstanceOf(Source::class, $source);
        $normalizedBody = preg_replace('/\s+/', '', $source->body);
        $this->assertEquals(1, substr_count($normalizedBody ?? '', 'scalarJSON'));
        $this->removeTempFiles([$file1, $file2]);
    }

    public function testAllowsIdenticalTypeDefinitions(): void
    {
        $type = "type Money { amount: Int! currency: String! }";
        $file1 = $this->createTempFile($type);
        $file2 = $this->createTempFile($type);
        $merger = new SchemaMerger();
        $source = $merger->merge([$file1, $file2]);
        $this->assertInstanceOf(Source::class, $source);
        $normalizedBody = preg_replace('/\s+/', '', $source->body);
        $this->assertEquals(1, substr_count($normalizedBody ?? '', 'typeMoney{amount:Int!currency:String!}'));
        $this->removeTempFiles([$file1, $file2]);
    }

    public function testThrowsOnNonIdenticalTypeDefinitions(): void
    {
        $file1 = $this->createTempFile('type Money { amount: Int! currency: String! }');
        $file2 = $this->createTempFile('type Money { amount: Int! currency: String! precision: Int! }');
        $merger = new SchemaMerger();
        $this->expectException(\RuntimeException::class);
        $this->expectOutputRegex('/Money/');
        $merger->merge([$file1, $file2]);
        $this->removeTempFiles([$file1, $file2]);
    }

    public function testMergesRootOperationTypes(): void
    {
        $file1 = $this->createTempFile('type Mutation { register(email: String!): String }');
        $file2 = $this->createTempFile('type Mutation { sendMagicLink(email: String!): String }');
        $merger = new SchemaMerger();
        $source = $merger->merge([$file1, $file2]);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertStringContainsIgnoringWhitespace('register(email: String!): String', $source->body);
        $this->assertStringContainsIgnoringWhitespace('sendMagicLink(email: String!): String', $source->body);
        $this->removeTempFiles([$file1, $file2]);
    }

    public function testThrowsOnDuplicateOperationFields(): void
    {
        $file1 = $this->createTempFile('type Mutation { foo: String }');
        $file2 = $this->createTempFile('type Mutation { foo: Int }');
        $merger = new SchemaMerger();
        $this->expectException(\RuntimeException::class);
        $this->expectOutputRegex('/foo/');
        $merger->merge([$file1, $file2]);
        $this->removeTempFiles([$file1, $file2]);
    }

    public function testLogsErrorsToConsole(): void
    {
        $file1 = $this->createTempFile('type Money { amount: Int! }');
        $file2 = $this->createTempFile('type Money { amount: String! }');
        $merger = new SchemaMerger();
        $this->expectException(\RuntimeException::class);
        $this->expectOutputRegex('/Money/');
        $merger->merge([$file1, $file2]);
        $this->removeTempFiles([$file1, $file2]);
    }

    public function testReturnsSourceObject(): void
    {
        $file = $this->createTempFile('type Query { foo: String }');
        $merger = new SchemaMerger();
        $source = $merger->merge([$file]);
        $this->assertInstanceOf(Source::class, $source);
        $this->removeTempFiles([$file]);
    }

    public function testHandlesEmptyInput(): void
    {
        $merger = new SchemaMerger();
        $this->expectException(\InvalidArgumentException::class);
        $merger->merge([]);
    }

    public function testThrowsOnInvalidGraphQL(): void
    {
        $file = $this->createTempFile('type Query { foo: }');
        $merger = new SchemaMerger();
        $this->expectException(\RuntimeException::class);
        $merger->merge([$file]);
        $this->removeTempFiles([$file]);
    }

    public function testThrowsOnMissingFiles(): void
    {
        $merger = new SchemaMerger();
        $this->expectException(\RuntimeException::class);
        $merger->merge(['/nonexistent/file.graphql']);
    }
}
