<?php

declare(strict_types=1);

namespace Neos\Flow\Mvc\Routing;

/**
 * Normalizer to convert route values when resolving a route to its primitive types
 *
 *     $uriBuilder->uriFor(
 *         'someThing',
 *         ['myObject' => $object]
 *     );
 *
 * This applies for internal exceeding arguments which are eventually encoded via http_build_query() for the uri.
 *
 * @internal
 */
interface RouteValuesNormalizerInterface
{
    /**
     * Normalize objects in an array to a set of arrays/scalars
     *
     * @param array<mixed> $array The array to be iterated over
     * @return array<mixed> The modified array without objects
     */
    public function normalizeObjects(array $array): array;
}
