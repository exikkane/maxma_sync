<?php

namespace Tygh\Addons\MaxmaSync\Helpers;

use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class RequestFactory
{
    /** @var array<string, array<string, object>> */
    private static array $called_payloads = [];
    /**
     * @param iterable<RequestBuilderInterface> $builders
     */
    public function __construct(
        private readonly iterable $builders,
        private readonly MaxmaLogger $logger = new MaxmaLogger()
    ) {}

    /**
     * @param array<array-key, mixed> $payload
     */
    public function make(string $method, array $payload): object
    {
        // используем кеш только для тех типов запроса, которые используются на витрине
        $payload_hash = in_array($method, RequestTypes::getQueueTypes(), true) ? null : $this->buildPayloadHash($payload);

        if ($payload_hash !== null) {

            if (isset(self::$called_payloads[$method][$payload_hash])) {
                return self::$called_payloads[$method][$payload_hash];
            }
        }

        foreach ($this->builders as $builder) {
            if ($builder->supports($method)) {
                $request = $builder->build($payload);
                if ($payload_hash !== null) {
                    self::$called_payloads[$method][$payload_hash] = $request;
                }

                return $request;
            }
        }
        $this->logger->error("Unknown MAXMA method: {$method}");

        throw new \InvalidArgumentException('Unknown MAXMA method: ' . $method);
    }
    /**
     * @param array<array-key, mixed> $payload
     */
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

