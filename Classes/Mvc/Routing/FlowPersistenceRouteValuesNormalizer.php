<?php

declare(strict_types=1);

namespace Neos\Flow\Mvc\Routing;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\UnknownObjectException;
use Neos\Flow\Persistence\PersistenceManagerInterface;

/**
 * @internal
 * @Flow\Scope("singleton")
 */
final readonly class FlowPersistenceRouteValuesNormalizer implements RouteValuesNormalizerInterface
{
    public function __construct(
        private PersistenceManagerInterface $persistenceManager
    ) {
    }

    /**
     * Recursively iterates through the given array and turns objects
     * into an arrays
     *
     * @param array<mixed> $array The array to be iterated over
     * @return array<mixed> The modified array without objects
     */
    public function normalizeObjects(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->normalizeObjects($value);
            } elseif (is_object($value) && $value instanceof \Traversable) {
                $array[$key] = $this->normalizeObjects(iterator_to_array($value));
            } elseif (is_object($value)) {
                $array[$key] = $this->normalizeObject($value);
            }
        }
        return $array;
    }

    private function normalizeObject(object $object): array
    {
        $identifier = $this->persistenceManager->getIdentifierByObject($object);
        if ($identifier === null) {
            throw new UnknownObjectException(sprintf('Tried to convert an object of type "%s" to an identity array, but it is unknown to the Persistence Manager.', $object::class), 1740046025);
        }
        return ['__identity' => $identifier];
    }
}
