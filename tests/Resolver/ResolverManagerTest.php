<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Resolver;

use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Middleware\Contract\ResolverInterface;
use GraphQL\Middleware\Factory\ResolverFactory;
use GraphQL\Middleware\Resolver\ResolverManager;
use GraphQL\Type\Definition\ResolveInfo;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;

class ResolverManagerTest extends TestCase
{
    private ResolverFactory&MockObject $resolverFactory;
    private ResolverManager $manager;
    private ResolverInterface $resolver;

    protected function setUp(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $this->resolverFactory = $this->getMockBuilder(ResolverFactory::class)
            ->setConstructorArgs([$container])
            ->onlyMethods(['createResolver'])
            ->getMock();
        $this->manager = new ResolverManager($this->resolverFactory);

        $this->resolver = new class implements ResolverInterface {
            public function __invoke($source, array $args, $context, ResolveInfo $info): mixed
            {
                return ['id' => '1'];
            }
        };
    }

    public function testCreateTypeConfigDecoratorWithQueryType(): void
    {
        $decorator = $this->manager->createTypeConfigDecorator();
        $typeDefinitionNode = $this->createMock(TypeDefinitionNode::class);
        $info = $this->createMock(ResolveInfo::class);
        $info->fieldName = 'getUser';

        $this->resolverFactory->expects($this->any())
            ->method('createResolver')
            ->with('GetUser')
            ->willReturn($this->resolver);

        $config = [
            'name' => 'Query',
            'fields' => [
                'getUser' => [
                    'type' => 'User'
                ]
            ]
        ];

        $result = $decorator($config, $typeDefinitionNode);
        $this->assertArrayHasKey('resolveField', $result);

        $resolveField = $result['resolveField'];
        $resolvedValue = $resolveField(null, [], null, $info);
        $this->assertEquals(['id' => '1'], $resolvedValue);
    }

    public function testCreateTypeConfigDecoratorWithMutationType(): void
    {
        $decorator = $this->manager->createTypeConfigDecorator();
        $typeDefinitionNode = $this->createMock(TypeDefinitionNode::class);
        $info = $this->createMock(ResolveInfo::class);
        $info->fieldName = 'createUser';

        $this->resolverFactory->expects($this->any())
            ->method('createResolver')
            ->with('CreateUser')
            ->willReturn($this->resolver);

        $config = [
            'name' => 'Mutation',
            'fields' => [
                'createUser' => [
                    'type' => 'User'
                ]
            ]
        ];

        $result = $decorator($config, $typeDefinitionNode);
        $this->assertArrayHasKey('resolveField', $result);

        $resolveField = $result['resolveField'];
        $resolvedValue = $resolveField(null, [], null, $info);
        $this->assertEquals(['id' => '1'], $resolvedValue);
    }

    public function testCreateTypeConfigDecoratorWithFallbackResolver(): void
    {
        $fallbackResolver = function ($source, $args, $context, $info) {
            return ['id' => '2'];
        };

        $manager = new ResolverManager($this->resolverFactory, $fallbackResolver);
        $decorator = $manager->createTypeConfigDecorator();
        $typeDefinitionNode = $this->createMock(TypeDefinitionNode::class);
        $info = $this->createMock(ResolveInfo::class);
        $info->fieldName = 'getUser';

        $this->resolverFactory->expects($this->any())
            ->method('createResolver')
            ->with('GetUser')
            ->willReturn(null);

        $config = [
            'name' => 'Query',
            'fields' => [
                'getUser' => [
                    'type' => 'User'
                ]
            ]
        ];

        $result = $decorator($config, $typeDefinitionNode);
        $this->assertArrayHasKey('resolveField', $result);

        $resolveField = $result['resolveField'];
        $resolvedValue = $resolveField(null, [], null, $info);
        $this->assertEquals(['id' => '2'], $resolvedValue);
    }

    public function testCreateTypeConfigDecoratorWithNonOperationType(): void
    {
        $decorator = $this->manager->createTypeConfigDecorator();
        $typeDefinitionNode = $this->createMock(TypeDefinitionNode::class);

        $config = [
            'name' => 'User',
            'fields' => [
                'id' => [
                    'type' => 'ID'
                ]
            ]
        ];

        $result = $decorator($config, $typeDefinitionNode);
        $this->assertSame($config, $result);
    }
}
