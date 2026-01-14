<?php

namespace Tygh\Addons\MaxmaSync\Helpers;

use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class RequestFactory
{
    /** @var iterable<RequestBuilderInterface> */
    private $builders;

    /** @var MaxmaLogger */
    private $logger;

    /** @var array */
    private static $called_payloads = [];

    /**
     * @param iterable<RequestBuilderInterface> $builders
     * @param MaxmaLogger|null $logger
     */
    public function __construct(iterable $builders, ?MaxmaLogger $logger = null)
    {
        $this->builders = $builders;
        $this->logger = $logger ?? new MaxmaLogger();
    }


    public function make(string $method, array $payload)
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
                $request = $builder->build($payload);
                if ($use_cache) {
                    self::$called_payloads[$method][$payload_hash] = $request;
                }

                return $request;
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

