<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

/**
 * Tiny exact-match router: `METHOD /path` → handler(Request): Response.
 */
final class Router
{
    /** @var array<string, array<string, Closure(Request): Response>> path → method → handler */
    private array $routes = [];

    /** @param callable(Request): Response $handler */
    public function get(string $path, callable $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    /** @param callable(Request): Response $handler */
    public function post(string $path, callable $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    /** @param callable(Request): Response $handler */
    public function add(string $method, string $path, callable $handler): self
    {
        $this->routes[Request::normalizePath($path)][strtoupper($method)] = Closure::fromCallable($handler);

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $handlers = $this->routes[$request->path] ?? null;

        if ($handlers === null) {
            throw new HttpException(404, 'not_found', 'The page you are looking for does not exist.');
        }

        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $handler = $handlers[$method] ?? null;

        if ($handler === null) {
            throw new HttpException(405, 'method_not_allowed', 'This endpoint does not support ' . $request->method . '.', headers: [
                'Allow' => implode(', ', array_keys($handlers)),
            ]);
        }

        return $handler($request);
    }
}
