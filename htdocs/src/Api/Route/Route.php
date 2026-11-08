<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Route;

/**
 * One route: a method, a path pattern and a handler.
 *
 * Patterns use `{name}` for a path segment that captures any non-slash run, so
 * `/api/v1/sessions/{id}/actions` matches `/api/v1/sessions/abc/actions` and
 * captures `id => abc`. Only the characters that are legal in an identifier
 * are accepted, which keeps a capture from smuggling a slash or a control
 * character into a downstream lookup.
 */
final class Route
{
    /** @var list<string> */
    private array $parameterNames = [];

    private string $regex;

    /**
     * @param callable(array<string,string>, \CasinoEngine\Api\Http\Request, \CasinoEngine\Api\Http\RequestContext): \CasinoEngine\Api\Http\Response $handler
     * @param list<string> $middleware
     */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        private readonly mixed $handler,
        public readonly array $middleware = []
    ) {
        $this->regex = $this->compile($pattern);
    }

    /**
     * Matches a path, returning the captured parameters or null.
     *
     * @return array<string, string>|null
     */
    public function match(string $method, string $path): ?array
    {
        if (strtoupper($method) !== $this->method) {
            return null;
        }

        $normalised = '/' . trim($path, '/');

        if (preg_match($this->regex, $normalised, $matches) !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($this->parameterNames as $name) {
            $parameters[$name] = $matches[$name] ?? '';
        }

        return $parameters;
    }

    /**
     * @param array<string, string> $parameters
     * @return \CasinoEngine\Api\Http\Response
     */
    public function invoke(
        array $parameters,
        \CasinoEngine\Api\Http\Request $request,
        \CasinoEngine\Api\Http\RequestContext $context
    ): \CasinoEngine\Api\Http\Response {
        /** @var callable $handler */
        $handler = $this->handler;

        return $handler($parameters, $request, $context);
    }

    private function compile(string $pattern): string
    {
        $normalised = '/' . trim($pattern, '/');
        $segments = $normalised === '/' ? [''] : explode('/', ltrim($normalised, '/'));
        $compiled = '';

        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $match) === 1) {
                $this->parameterNames[] = $match[1];
                // A capture never spans a slash and never contains a control
                // character, so it cannot escape its segment.
                $compiled .= '/(?P<' . $match[1] . '>[A-Za-z0-9._:-]+)';
                continue;
            }

            $compiled .= '/' . preg_quote($segment, '#');
        }

        return '#^' . $compiled . '$#';
    }
}
