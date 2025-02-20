<?php

declare(strict_types=1);

namespace Neos\Flow\Mvc\Routing;

/**
 * @internal
 */
interface RouteValuesNormalizerInterface
{
    /**
     * Normalizes an object into a set of arrays/scalars
     *
     * @param array<mixed> $array The array to be iterated over
     * @return array<mixed> The modified array without objects
     */
    public function normalizeObjects(array $array): array;
}
