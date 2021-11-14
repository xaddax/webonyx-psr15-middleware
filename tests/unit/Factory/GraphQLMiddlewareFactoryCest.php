<?php
declare(strict_types=1);

namespace Tests\Unit\Factory;

use GraphQL\Server\StandardServer;
use Prophecy\Prophet;
use Psr\Container\ContainerInterface;
use UnitTester;
use Zestic\GraphQL\Middleware\Factory\GraphQLMiddlewareFactory;
use Zestic\GraphQL\Middleware\GraphQLMiddleware;

class GraphQLMiddlewareFactoryCest
{
    public function testInvoke(UnitTester $I)
    {
        $prophet = new Prophet();
        $prophecy = $prophet->prophesize();
        $prophecy->willImplement(ContainerInterface::class);
        $prophecy->get(StandardServer::class)->willReturn(new StandardServer([]));
        $config = [
            'graphQL' => [
                'middleware' => [
                    'allowedHeaders' => [
                        'application/json',
                    ],
                ],
            ],
        ];
        $prophecy->get('config')->willReturn($config);
        $container = $prophecy->reveal();

        $middleware = (new GraphQLMiddlewareFactory())->__invoke($container);

        $I->assertInstanceOf(GraphQLMiddleware::class, $middleware);
    }
}
