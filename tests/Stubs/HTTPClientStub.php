<?php

declare(strict_types=1);

namespace TerryApiBundle\Tests\Stubs;

use Symfony\Component\HttpFoundation\Request;
use TerryApiBundle\ValueObject\AbstractHTTPClient;
use TerryApiBundle\ValueObject\HTTPServer;

class HTTPClientStub extends AbstractHTTPClient
{
    public static function fromRequest(Request $request, HTTPServer $httpServer): self
    {
        return new self($request->headers, $httpServer);
    }

    public function get(string $property)
    {
        return $this->$property;
    }

    public function negotiateProperty(string $subject, string $property, $defaults = [], $availables = [])
    {
        return $this->negotiate($subject, $property, $defaults, $availables);
    }
}