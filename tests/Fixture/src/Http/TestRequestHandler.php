<?php

declare(strict_types=1);

namespace Test\Fixture\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TestRequestHandler implements RequestHandlerInterface
{
    /** @var ResponseInterface */
    private $response;

    public function __construct(string $message = '', int $responseCode = 200)
    {
        $data = [
            'message' => $message,
        ];
        $encodedMessage = json_encode($data);
        if ($encodedMessage !== false) {
            $this->response = new Response($responseCode);
            $this->response->getBody()->write($encodedMessage);
            $this->response = $this->response->withHeader('Content-Type', 'application/json');
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}
