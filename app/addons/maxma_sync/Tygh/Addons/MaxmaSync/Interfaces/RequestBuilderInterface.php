<?php

namespace Tygh\Addons\MaxmaSync\Interfaces;

interface RequestBuilderInterface
{
    /**
     * Может ли билдер обрабатывать данный метод
     */
    public function supports(string $method): bool;

    /**
     * Собирает SDK-запрос из payload
     */
    public function build(array $payload): object;
}