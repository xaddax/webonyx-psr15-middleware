<?php

declare(strict_types=1);

namespace GraphQL\Middleware\Tests\Context;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Middleware\Context\RequestContext;
use GraphQL\Server\OperationParams;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class RequestContextTest extends TestCase
{
    private RequestContext $context;
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        $this->context = new RequestContext();
        $this->request = new ServerRequest(
            'POST',
            'https://example.com/graphql',
            [
                'Authorization' => 'Bearer token123',
                'X-CLIENT-ID' => 'client456',
                'User-Agent' => 'TestAgent/1.0',
                'Content-Type' => 'application/json'
            ],
            '{"query": "{ hello }"}',
            '1.1',
            [
                'HTTP_HOST' => 'example.com',
                'REQUEST_METHOD' => 'POST',
                'SERVER_NAME' => 'example.com'
            ]
        );
        
        // Add query params and cookies
        $this->request = $this->request
            ->withQueryParams(['debug' => '1', 'version' => '2'])
            ->withCookieParams(['session' => 'abc123', 'preferences' => 'dark'])
            ->withParsedBody(['query' => '{ hello }', 'variables' => []]);
    }

    public function testInvokeReturnsContext(): void
    {
        $params = $this->createMock(OperationParams::class);
        $doc = $this->createMock(DocumentNode::class);
        $operationType = 'query';

        $result = ($this->context)($params, $doc, $operationType);
        
        $this->assertSame($this->context, $result);
    }

    public function testSetAndGetRequest(): void
    {
        $this->context->setRequest($this->request);
        $retrievedRequest = $this->context->getRequest();
        
        $this->assertSame($this->request, $retrievedRequest);
    }

    public function testGetRequestThrowsExceptionWhenNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set on GraphQL context');
        
        $this->context->getRequest();
    }

    public function testGetToken(): void
    {
        $this->context->setRequest($this->request);
        
        $token = $this->context->getToken();
        $this->assertEquals('Bearer token123', $token);
    }

    public function testGetTokenReturnsNullWhenHeaderMissing(): void
    {
        $requestWithoutAuth = new ServerRequest('POST', 'https://example.com/graphql');
        $this->context->setRequest($requestWithoutAuth);
        
        $token = $this->context->getToken();
        $this->assertNull($token);
    }

    public function testGetTokenReturnsNullWhenHeaderEmpty(): void
    {
        $requestWithEmptyAuth = new ServerRequest(
            'POST', 
            'https://example.com/graphql',
            ['Authorization' => '']
        );
        $this->context->setRequest($requestWithEmptyAuth);
        
        $token = $this->context->getToken();
        $this->assertNull($token);
    }

    public function testGetClientId(): void
    {
        $this->context->setRequest($this->request);
        
        $clientId = $this->context->getClientId();
        $this->assertEquals('client456', $clientId);
    }

    public function testGetClientIdReturnsNullWhenHeaderMissing(): void
    {
        $requestWithoutClientId = new ServerRequest('POST', 'https://example.com/graphql');
        $this->context->setRequest($requestWithoutClientId);
        
        $clientId = $this->context->getClientId();
        $this->assertNull($clientId);
    }

    public function testGetClientIdReturnsNullWhenHeaderEmpty(): void
    {
        $requestWithEmptyClientId = new ServerRequest(
            'POST', 
            'https://example.com/graphql',
            ['X-CLIENT-ID' => '']
        );
        $this->context->setRequest($requestWithEmptyClientId);
        
        $clientId = $this->context->getClientId();
        $this->assertNull($clientId);
    }

    public function testGetUserAgent(): void
    {
        $this->context->setRequest($this->request);
        
        $userAgent = $this->context->getUserAgent();
        $this->assertEquals('TestAgent/1.0', $userAgent);
    }

    public function testGetUserAgentReturnsNullWhenHeaderMissing(): void
    {
        $requestWithoutUserAgent = new ServerRequest('POST', 'https://example.com/graphql');
        $this->context->setRequest($requestWithoutUserAgent);
        
        $userAgent = $this->context->getUserAgent();
        $this->assertNull($userAgent);
    }

    public function testGetUserAgentReturnsNullWhenHeaderEmpty(): void
    {
        $requestWithEmptyUserAgent = new ServerRequest(
            'POST', 
            'https://example.com/graphql',
            ['User-Agent' => '']
        );
        $this->context->setRequest($requestWithEmptyUserAgent);
        
        $userAgent = $this->context->getUserAgent();
        $this->assertNull($userAgent);
    }

    public function testGetMethod(): void
    {
        $this->context->setRequest($this->request);
        
        $method = $this->context->getMethod();
        $this->assertEquals('POST', $method);
    }

    public function testGetMethodWithDifferentMethods(): void
    {
        $getRequest = new ServerRequest('GET', 'https://example.com/graphql');
        $this->context->setRequest($getRequest);
        $this->assertEquals('GET', $this->context->getMethod());

        $putRequest = new ServerRequest('PUT', 'https://example.com/graphql');
        $this->context->setRequest($putRequest);
        $this->assertEquals('PUT', $this->context->getMethod());
    }

    public function testGetQueryParams(): void
    {
        $this->context->setRequest($this->request);
        
        $queryParams = $this->context->getQueryParams();
        $this->assertEquals(['debug' => '1', 'version' => '2'], $queryParams);
    }

    public function testGetQueryParamsReturnsEmptyArrayWhenNone(): void
    {
        $requestWithoutQuery = new ServerRequest('POST', 'https://example.com/graphql');
        $this->context->setRequest($requestWithoutQuery);
        
        $queryParams = $this->context->getQueryParams();
        $this->assertEquals([], $queryParams);
    }

    public function testGetParsedBody(): void
    {
        $this->context->setRequest($this->request);
        
        $parsedBody = $this->context->getParsedBody();
        $this->assertEquals(['query' => '{ hello }', 'variables' => []], $parsedBody);
    }

    public function testGetParsedBodyReturnsNullWhenNone(): void
    {
        $requestWithoutBody = new ServerRequest('POST', 'https://example.com/graphql');
        $this->context->setRequest($requestWithoutBody);
        
        $parsedBody = $this->context->getParsedBody();
        $this->assertNull($parsedBody);
    }

    public function testGetCookieParams(): void
    {
        $this->context->setRequest($this->request);
        
        $cookieParams = $this->context->getCookieParams();
        $this->assertEquals(['session' => 'abc123', 'preferences' => 'dark'], $cookieParams);
    }

    public function testGetCookieParamsReturnsEmptyArrayWhenNone(): void
    {
        $requestWithoutCookies = new ServerRequest('POST', 'https://example.com/graphql');
        $this->context->setRequest($requestWithoutCookies);
        
        $cookieParams = $this->context->getCookieParams();
        $this->assertEquals([], $cookieParams);
    }

    public function testGetServerParams(): void
    {
        $this->context->setRequest($this->request);
        
        $serverParams = $this->context->getServerParams();
        $this->assertArrayHasKey('HTTP_HOST', $serverParams);
        $this->assertArrayHasKey('REQUEST_METHOD', $serverParams);
        $this->assertArrayHasKey('SERVER_NAME', $serverParams);
        $this->assertEquals('example.com', $serverParams['HTTP_HOST']);
        $this->assertEquals('POST', $serverParams['REQUEST_METHOD']);
        $this->assertEquals('example.com', $serverParams['SERVER_NAME']);
    }

    public function testGetServerParamsReturnsEmptyArrayWhenNone(): void
    {
        $requestWithoutServerParams = new ServerRequest('POST', 'https://example.com/graphql');
        $this->context->setRequest($requestWithoutServerParams);

        $serverParams = $this->context->getServerParams();
        $this->assertEquals([], $serverParams);
    }

    public function testGetTokenThrowsExceptionWhenRequestNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set on GraphQL context');

        $this->context->getToken();
    }

    public function testGetClientIdThrowsExceptionWhenRequestNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set on GraphQL context');

        $this->context->getClientId();
    }

    public function testGetUserAgentThrowsExceptionWhenRequestNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set on GraphQL context');

        $this->context->getUserAgent();
    }

    public function testGetMethodThrowsExceptionWhenRequestNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set on GraphQL context');

        $this->context->getMethod();
    }

    public function testGetQueryParamsThrowsExceptionWhenRequestNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set on GraphQL context');

        $this->context->getQueryParams();
    }

    public function testGetParsedBodyThrowsExceptionWhenRequestNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set on GraphQL context');

        $this->context->getParsedBody();
    }

    public function testGetCookieParamsThrowsExceptionWhenRequestNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set on GraphQL context');

        $this->context->getCookieParams();
    }

    public function testGetServerParamsThrowsExceptionWhenRequestNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request not set on GraphQL context');

        $this->context->getServerParams();
    }
}
