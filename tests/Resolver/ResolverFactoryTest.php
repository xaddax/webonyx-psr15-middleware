<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Resolver;

use GraphQL\Middleware\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use GraphQL\Middleware\Factory\ResolverFactory;
use GraphQL\Middleware\Contract\ResolverInterface;
use GraphQL\Type\Definition\ResolveInfo;

class ResolverFactoryTest extends TestCase
{
    private ContainerInterface&MockObject $container;
    private ResolverFactory $factory;

    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->onlyMethods(['has', 'get'])
            ->getMock();
        $this->factory = new ResolverFactory($this->container);
    }

    public function testCreateResolverWithExistingClass(): void
    {
        $resolverClass = 'App\\GraphQL\\Resolver\\GetUserResolver';
        $resolver = new class implements ResolverInterface {
            public function __invoke($source, array $args, $context, ResolveInfo $info): mixed
            {
                return ['id' => '1'];
            }
        };

        $this->mockClassExists($resolverClass);

        $this->container->expects($this->once())
            ->method('has')
            ->with($resolverClass)
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with($resolverClass)
            ->willReturn($resolver);

        $result = $this->factory->createResolver('GetUser');
        $this->assertSame($resolver, $result);
    }

    public function testCreateResolverWithNonExistentClass(): void
    {
        $result = $this->factory->createResolver('NonExistent');
        $this->assertNull($result);
    }

    public function testCreateResolverWithCustomNamespace(): void
    {
        $resolverClass = 'Custom\\Namespace\\GetUserResolver';
        $resolver = new class implements ResolverInterface {
            public function __invoke($source, array $args, $context, ResolveInfo $info): mixed
            {
                return ['id' => '1'];
            }
        };

        $factory = new ResolverFactory($this->container, 'Custom\Namespace');

        $this->mockClassExists($resolverClass);

        $this->container->expects($this->once())
            ->method('has')
            ->with($resolverClass)
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with($resolverClass)
            ->willReturn($resolver);

        $result = $factory->createResolver('GetUser');
        $this->assertSame($resolver, $result);
    }
}
