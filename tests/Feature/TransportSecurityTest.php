<?php

namespace Tests\Feature;

use App\Http\Middleware\ForceHttps;
use Illuminate\Http\Request;
use Tests\TestCase;

class TransportSecurityTest extends TestCase
{
    public function test_production_http_requests_are_redirected_to_https(): void
    {
        app()->instance('env', 'production');
        config(['app.url' => 'http://example.test']);

        $request = Request::create('http://example.test/client/login', 'GET');
        $response = (new ForceHttps())->handle($request, fn () => response('ok'));

        $this->assertSame(308, $response->getStatusCode());
        $this->assertSame('https://example.test/client/login', $response->headers->get('location'));

        app()->instance('env', 'testing');
    }

    public function test_https_requests_are_not_redirected(): void
    {
        app()->instance('env', 'production');
        config(['app.url' => 'https://example.test']);

        $request = Request::create('https://example.test/client/login', 'GET');
        $response = (new ForceHttps())->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());

        app()->instance('env', 'testing');
    }
}