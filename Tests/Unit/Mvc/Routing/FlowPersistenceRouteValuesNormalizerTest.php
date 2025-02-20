<?php

declare(strict_types=1);

namespace Neos\Flow\Tests\Unit\Mvc\Routing;

use Neos\Flow\Mvc\Routing\FlowPersistenceRouteValuesNormalizer;
use Neos\Flow\Persistence\Exception\UnknownObjectException;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class FlowPersistenceRouteValuesNormalizerTest extends UnitTestCase
{
    /**
     * @var PersistenceManagerInterface&MockObject
     */
    protected $persistenceManager;

    /**
     * @var FlowPersistenceRouteValuesNormalizer
     */
    protected $flowPersistenceRouteValuesNormalizer;

    protected function setUp(): void
    {
        $this->persistenceManager = $this->getMockBuilder(PersistenceManagerInterface::class)->getMock();

        $this->flowPersistenceRouteValuesNormalizer = new FlowPersistenceRouteValuesNormalizer($this->persistenceManager);
    }

    /**
     * @test
     */
    public function normalizeObjectsConvertsAnObject()
    {
        $someObject = new \stdClass();
        $this->persistenceManager->expects(self::once())->method('getIdentifierByObject')->with($someObject)->will(self::returnValue(123));

        $expectedResult = [['__identity' => 123]];
        $actualResult = $this->flowPersistenceRouteValuesNormalizer->normalizeObjects([$someObject]);
        self::assertEquals($expectedResult, $actualResult);
    }


    /**
     * @test
     */
    public function convertObjectToIdentityArrayThrowsExceptionIfIdentityForTheGivenObjectCantBeDetermined()
    {
        $this->expectException(UnknownObjectException::class);
        $someObject = new \stdClass();
        $this->persistenceManager->expects(self::once())->method('getIdentifierByObject')->with($someObject)->will(self::returnValue(null));

        $this->flowPersistenceRouteValuesNormalizer->normalizeObjects([$someObject]);
    }

    /**
     * @test
     */
    public function convertObjectsToIdentityArraysRecursivelyConvertsObjects()
    {
        $object1 = new \stdClass();
        $object2 = new \stdClass();
        $this->persistenceManager->expects(self::exactly(2))->method('getIdentifierByObject')
            ->withConsecutive([$object1], [$object2])->willReturnOnConsecutiveCalls('identifier1', 'identifier2');

        $originalArray = ['foo' => 'bar', 'object1' => $object1, 'baz' => ['object2' => $object2]];
        $expectedResult = ['foo' => 'bar', 'object1' => ['__identity' => 'identifier1'], 'baz' => ['object2' => ['__identity' => 'identifier2']]];

        $actualResult = $this->flowPersistenceRouteValuesNormalizer->normalizeObjects($originalArray);
        self::assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function convertObjectsToIdentityArraysConvertsObjectsInIterators()
    {
        $object1 = new \stdClass();
        $object2 = new \stdClass();
        $this->persistenceManager->expects(self::exactly(2))->method('getIdentifierByObject')
            ->withConsecutive([$object1], [$object2])->willReturnOnConsecutiveCalls('identifier1', 'identifier2');

        $originalArray = ['foo' => 'bar', 'object1' => $object1, 'baz' => new \ArrayObject(['object2' => $object2])];
        $expectedResult = ['foo' => 'bar', 'object1' => ['__identity' => 'identifier1'], 'baz' => ['object2' => ['__identity' => 'identifier2']]];

        $actualResult = $this->flowPersistenceRouteValuesNormalizer->normalizeObjects($originalArray);
        self::assertEquals($expectedResult, $actualResult);
    }
}
