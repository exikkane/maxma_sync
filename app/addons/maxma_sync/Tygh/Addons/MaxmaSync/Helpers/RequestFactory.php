<?php

namespace Tygh\Addons\MaxmaSync\Helpers;

use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;

final class RequestFactory
{
    /**
     * @param iterable<RequestBuilderInterface> $builders
     */
    public function __construct(
        private readonly iterable $builders
    ) {}

    public function make(string $method, array $payload): object
    {
        foreach ($this->builders as $builder) {
            if ($builder->supports($method)) {
                return $builder->build($payload);
            }
        }

        throw new \InvalidArgumentException("Unknown MAXMA method: {$method}");
    }
}

