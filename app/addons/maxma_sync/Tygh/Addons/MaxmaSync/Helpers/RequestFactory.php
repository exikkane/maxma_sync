<?php

namespace Tygh\Addons\MaxmaSync\Helpers;

use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class RequestFactory
{
    private static array $called_payloads = [];
    /**
     * @param iterable<RequestBuilderInterface> $builders
     */
    public function __construct(
        private readonly iterable $builders,
        private readonly MaxmaLogger $logger = new MaxmaLogger()
    ) {}

    public function make(string $method, array $payload): object|bool
    {
        // используем кеш только для тех типов запроса, которые используются на витрине
        $use_cache = !in_array($method, RequestTypes::getQueueTypes(), true);

        if ($use_cache) {
            $payload_hash = $this->buildPayloadHash($payload);

            if (isset(self::$called_payloads[$method][$payload_hash])) {
                return self::$called_payloads[$method][$payload_hash];
            }
        }

        foreach ($this->builders as $builder) {
            if ($builder->supports($method)) {
                $response = $builder->build($payload);
                if ($use_cache) {
                    self::$called_payloads[$method][$payload_hash] = $response;
                }

                return $response;
            }
        }
        $this->logger->error("Unknown MAXMA method: {$method}");

        return false;
    }
    private function buildPayloadHash(array $payload): string
    {
        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            )
        );
    }

}

