<?php
namespace TYPO3\Flow\Tests\Unit\Property\TypeConverter;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Fixtures\ClassWithSetters;
use TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Property\PropertyMappingConfiguration;
use TYPO3\Flow\Property\TypeConverter\Error\TargetNotFoundError;
use TYPO3\Flow\Property\TypeConverter\PersistentObjectConverter;
use TYPO3\Flow\Property\TypeConverterInterface;
use TYPO3\Flow\Reflection\ReflectionService;
use TYPO3\Flow\Tests\UnitTestCase;

require_once(__DIR__ . '/../../Fixtures/ClassWithSetters.php');
require_once(__DIR__ . '/../../Fixtures/ClassWithSettersAndConstructor.php');

/**
 * Testcase for the PersistentObjectConverter
 */
class PersistentObjectConverterTest extends UnitTestCase
{
    /**
     * @var TypeConverterInterface
     */
    protected $converter;

    /**
     * @var ReflectionService|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockReflectionService;

    /**
     * @var PersistenceManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockPersistenceManager;

    /**
     * @var ObjectManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockObjectManager;

    public function setUp()
    {
        $this->converter = new PersistentObjectConverter();
        $this->mockReflectionService = $this->getMock('TYPO3\Flow\Reflection\ReflectionService');
        $this->inject($this->converter, 'reflectionService', $this->mockReflectionService);

        $this->mockPersistenceManager = $this->getMock('TYPO3\Flow\Persistence\PersistenceManagerInterface');
        $this->inject($this->converter, 'persistenceManager', $this->mockPersistenceManager);

        $this->mockObjectManager = $this->getMock('TYPO3\Flow\Object\ObjectManagerInterface');
        $this->inject($this->converter, 'objectManager', $this->mockObjectManager);
    }

    /**
     * @test
     */
    public function checkMetadata()
    {
        $this->assertEquals(array('string', 'array'), $this->converter->getSupportedSourceTypes(), 'Source types do not match');
        $this->assertEquals('object', $this->converter->getSupportedTargetType(), 'Target type does not match');
        $this->assertEquals(1, $this->converter->getPriority(), 'Priority does not match');
    }

    /**
     * @return array
     */
    public function dataProviderForCanConvert()
    {
        return array(
            array(true, false, true), // is entity => can convert
            array(false, true, true), // is valueobject => can convert
            array(false, false, false) // is no entity and no value object => can not convert
        );
    }

    /**
     * @test
     * @param boolean $isEntity
     * @param boolean $isValueObject
     * @param boolean $expected
     * @dataProvider dataProviderForCanConvert
     */
    public function canConvertFromReturnsTrueIfClassIsTaggedWithEntityOrValueObject($isEntity, $isValueObject, $expected)
    {
        if ($isEntity) {
            $this->mockReflectionService->expects($this->once())->method('isClassAnnotatedWith')->with('TheTargetType', 'TYPO3\Flow\Annotations\Entity')->will($this->returnValue($isEntity));
        } else {
            $this->mockReflectionService->expects($this->at(0))->method('isClassAnnotatedWith')->with('TheTargetType', 'TYPO3\Flow\Annotations\Entity')->will($this->returnValue($isEntity));
            $this->mockReflectionService->expects($this->at(1))->method('isClassAnnotatedWith')->with('TheTargetType', 'TYPO3\Flow\Annotations\ValueObject')->will($this->returnValue($isValueObject));
        }

        $this->assertEquals($expected, $this->converter->canConvertFrom('myInputData', 'TheTargetType'));
    }

    /**
     * @test
     */
    public function getSourceChildPropertiesToBeConvertedReturnsAllPropertiesExceptTheIdentityProperty()
    {
        $source = array(
            'k1' => 'v1',
            '__identity' => 'someIdentity',
            'k2' => 'v2'
        );
        $expected = array(
            'k1' => 'v1',
            'k2' => 'v2'
        );
        $this->assertEquals($expected, $this->converter->getSourceChildPropertiesToBeConverted($source));
    }

    /**
     * @test
     */
    public function getTypeOfChildPropertyShouldUseReflectionServiceToDetermineType()
    {
        $mockSchema = $this->getMockBuilder('TYPO3\Flow\Reflection\ClassSchema')->disableOriginalConstructor()->getMock();
        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('TheTargetType')->will($this->returnValue($mockSchema));

        $mockSchema->expects($this->any())->method('hasProperty')->with('thePropertyName')->will($this->returnValue(true));
        $mockSchema->expects($this->any())->method('getProperty')->with('thePropertyName')->will($this->returnValue(array(
            'type' => 'TheTypeOfSubObject',
            'elementType' => null
        )));
        $configuration = $this->buildConfiguration(array());
        $this->assertEquals('TheTypeOfSubObject', $this->converter->getTypeOfChildProperty('TheTargetType', 'thePropertyName', $configuration));
    }

    /**
     * @test
     */
    public function getTypeOfChildPropertyShouldUseConfiguredTypeIfItWasSet()
    {
        $this->mockReflectionService->expects($this->never())->method('getClassSchema');

        $configuration = $this->buildConfiguration(array());
        $configuration->forProperty('thePropertyName')->setTypeConverterOption('TYPO3\Flow\Property\TypeConverter\PersistentObjectConverter', PersistentObjectConverter::CONFIGURATION_TARGET_TYPE, 'Foo\Bar');
        $this->assertEquals('Foo\Bar', $this->converter->getTypeOfChildProperty('foo', 'thePropertyName', $configuration));
    }

    /**
     * @test
     */
    public function getTypeOfChildPropertyShouldConsiderSetters()
    {
        $mockSchema = $this->getMockBuilder('TYPO3\Flow\Reflection\ClassSchema')->disableOriginalConstructor()->getMock();
        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('TheTargetType')->will($this->returnValue($mockSchema));

        $mockSchema->expects($this->any())->method('hasProperty')->with('virtualPropertyName')->will($this->returnValue(false));

        $this->mockReflectionService->expects($this->any())->method('hasMethod')->with('TheTargetType', 'setVirtualPropertyName')->will($this->returnValue(true));
        $this->mockReflectionService->expects($this->any())->method('getMethodParameters')->with('TheTargetType', 'setVirtualPropertyName')->will($this->returnValue(array(
            array('type' => 'TheTypeOfSubObject')
        )));

        $this->mockReflectionService->expects($this->any())->method('hasMethod')->with('TheTargetType', 'setVirtualPropertyName')->will($this->returnValue(TRUE));
        $this->mockReflectionService
            ->expects($this->exactly(2))
            ->method('getMethodParameters')
            ->withConsecutive(
                array($this->equalTo('TheTargetType'), $this->equalTo('__construct')),
                array($this->equalTo('TheTargetType'), $this->equalTo('setVirtualPropertyName'))
            )
            ->will($this->returnValue(array(
                array('type' => 'TheTypeOfSubObject')
        )));
        $configuration = $this->buildConfiguration(array());
        $this->assertEquals('TheTypeOfSubObject', $this->converter->getTypeOfChildProperty('TheTargetType', 'virtualPropertyName', $configuration));
    }
    /**
     * @test
     */
    public function getTypeOfChildPropertyShouldConsiderConstructors() {
        $mockSchema = $this->getMockBuilder(\TYPO3\Flow\Reflection\ClassSchema::class)->disableOriginalConstructor()->getMock();
        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('TheTargetType')->will($this->returnValue($mockSchema));
        $this->mockReflectionService
            ->expects($this->exactly(1))
            ->method('getMethodParameters')
            ->with('TheTargetType', '__construct')
            ->will($this->returnValue(array(
                'anotherProperty' => array('type' => 'string')
        )));

        $configuration = $this->buildConfiguration(array());
        $this->assertEquals('string', $this->converter->getTypeOfChildProperty('TheTargetType', 'anotherProperty', $configuration));
    }


    /**
     * @test
     */
    public function convertFromShouldFetchObjectFromPersistenceIfUuidStringIsGiven()
    {
        $identifier = '550e8400-e29b-11d4-a716-446655440000';
        $object = new \stdClass();


        $this->mockPersistenceManager->expects($this->once())->method('getObjectByIdentifier')->with($identifier)->will($this->returnValue($object));
        $this->assertSame($object, $this->converter->convertFrom($identifier, 'MySpecialType'));
    }

    /**
     * @test
     */
    public function convertFromShouldFetchObjectFromPersistenceIfNonUuidStringIsGiven()
    {
        $identifier = 'someIdentifier';
        $object = new \stdClass();

        $this->mockPersistenceManager->expects($this->once())->method('getObjectByIdentifier')->with($identifier)->will($this->returnValue($object));
        $this->assertSame($object, $this->converter->convertFrom($identifier, 'MySpecialType'));
    }

    /**
     * @test
     */
    public function convertFromShouldFetchObjectFromPersistenceIfOnlyIdentityArrayGiven()
    {
        $identifier = '550e8400-e29b-11d4-a716-446655440000';
        $object = new \stdClass();

        $source = array(
            '__identity' => $identifier
        );
        $this->mockPersistenceManager->expects($this->once())->method('getObjectByIdentifier')->with($identifier)->will($this->returnValue($object));
        $this->assertSame($object, $this->converter->convertFrom($source, 'MySpecialType'));
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Property\Exception\InvalidPropertyMappingConfigurationException
     */
    public function convertFromShouldThrowExceptionIfObjectNeedsToBeModifiedButConfigurationIsNotSet()
    {
        $identifier = '550e8400-e29b-11d4-a716-446655440000';
        $object = new \stdClass();
        $object->someProperty = 'asdf';

        $source = array(
            '__identity' => $identifier,
            'foo' => 'bar'
        );
        $this->mockPersistenceManager->expects($this->once())->method('getObjectByIdentifier')->with($identifier)->will($this->returnValue($object));
        $this->converter->convertFrom($source, 'MySpecialType', array('foo' => 'bar'));
    }

    /**
     * @test
     */
    public function convertFromReturnsTargetNotFoundErrorIfHandleArrayDataFails()
    {
        $identifier = '550e8400-e29b-11d4-a716-446655440000';
        $object = new \stdClass();
        $object->someProperty = 'asdf';

        $source = array(
            '__identity' => $identifier,
            'foo' => 'bar'
        );
        $this->mockPersistenceManager->expects($this->once())->method('getObjectByIdentifier')->with($identifier)->will($this->returnValue(null));
        $actualResult = $this->converter->convertFrom($source, 'MySpecialType', array('foo' => 'bar'));

        $this->assertInstanceOf(TargetNotFoundError::class, $actualResult);
    }

    /**
     * @param array $typeConverterOptions
     * @return PropertyMappingConfiguration
     */
    protected function buildConfiguration($typeConverterOptions)
    {
        $configuration = new PropertyMappingConfiguration();
        $configuration->setTypeConverterOptions('TYPO3\Flow\Property\TypeConverter\PersistentObjectConverter', $typeConverterOptions);
        return $configuration;
    }

    /**
     * @param integer $numberOfResults
     * @param \PHPUnit_Framework_MockObject_Matcher_Invocation $howOftenIsGetFirstCalled
     * @return \stdClass
     */
    public function setupMockQuery($numberOfResults, $howOftenIsGetFirstCalled)
    {
        $mockClassSchema = $this->getMock('TYPO3\Flow\Reflection\ClassSchema', array(), array('Dummy'));
        $mockClassSchema->expects($this->once())->method('getIdentityProperties')->will($this->returnValue(array('key1' => 'someType')));
        $this->mockReflectionService->expects($this->once())->method('getClassSchema')->with('SomeType')->will($this->returnValue($mockClassSchema));

        $mockConstraint = $this->getMockBuilder('TYPO3\Flow\Persistence\Generic\Qom\Comparison')->disableOriginalConstructor()->getMock();

        $mockObject = new \stdClass();
        $mockQuery = $this->getMock('TYPO3\Flow\Persistence\QueryInterface');
        $mockQueryResult = $this->getMock('TYPO3\Flow\Persistence\QueryResultInterface');
        $mockQueryResult->expects($this->once())->method('count')->will($this->returnValue($numberOfResults));
        $mockQueryResult->expects($howOftenIsGetFirstCalled)->method('getFirst')->will($this->returnValue($mockObject));
        $mockQuery->expects($this->once())->method('equals')->with('key1', 'value1')->will($this->returnValue($mockConstraint));
        $mockQuery->expects($this->once())->method('matching')->with($mockConstraint)->will($this->returnValue($mockQuery));
        $mockQuery->expects($this->once())->method('execute')->will($this->returnValue($mockQueryResult));

        $this->mockPersistenceManager->expects($this->once())->method('createQueryForType')->with('SomeType')->will($this->returnValue($mockQuery));

        return $mockObject;
    }

    /**
     * @test
     */
    public function convertFromShouldReturnFirstMatchingObjectIfMultipleIdentityPropertiesExist()
    {
        $mockObject = $this->setupMockQuery(1, $this->once());

        $source = array(
            '__identity' => array('key1' => 'value1', 'key2' => 'value2')
        );
        $actual = $this->converter->convertFrom($source, 'SomeType');
        $this->assertSame($mockObject, $actual);
    }

    /**
     * @test
     */
    public function convertFromShouldReturnTargetNotFoundErrorIfNoMatchingObjectWasFound()
    {
        $this->setupMockQuery(0, $this->never());

        $source = array(
            '__identity' => array('key1' => 'value1', 'key2' => 'value2')
        );
        $actual = $this->converter->convertFrom($source, 'SomeType');
        $this->assertInstanceOf('TYPO3\Flow\Property\TypeConverter\Error\TargetNotFoundError', $actual);
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Property\Exception\InvalidSourceException
     */
    public function convertFromShouldThrowExceptionIfIdentityIsOfInvalidType()
    {
        $source = array(
            '__identity' => new \stdClass(),
        );
        $this->converter->convertFrom($source, 'SomeType');
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Property\Exception\DuplicateObjectException
     */
    public function convertFromShouldThrowExceptionIfMoreThanOneObjectWasFound()
    {
        $this->setupMockQuery(2, $this->never());

        $source = array(
            '__identity' => array('key1' => 'value1', 'key2' => 'value2')
        );
        $this->converter->convertFrom($source, 'SomeType');
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Property\Exception\InvalidPropertyMappingConfigurationException
     */
    public function convertFromShouldThrowExceptionIfObjectNeedsToBeCreatedButConfigurationIsNotSet()
    {
        $source = array(
            'foo' => 'bar'
        );
        $this->converter->convertFrom($source, 'MySpecialType');
    }

    /**
     * @test
     */
    public function convertFromShouldCreateObject()
    {
        $source = array(
            'propertyX' => 'bar'
        );
        $convertedChildProperties = array(
            'property1' => 'bar'
        );
        $expectedObject = new ClassWithSetters();
        $expectedObject->property1 = 'bar';

        $this->mockReflectionService->expects($this->once())->method('hasMethod')->with('TYPO3\Flow\Fixtures\ClassWithSetters', '__construct')->will($this->returnValue(false));
        $this->mockObjectManager->expects($this->once())->method('getClassNameByObjectName')->with('TYPO3\Flow\Fixtures\ClassWithSetters')->will($this->returnValue('TYPO3\Flow\Fixtures\ClassWithSetters'));
        $configuration = $this->buildConfiguration(array(PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED => true));
        $result = $this->converter->convertFrom($source, 'TYPO3\Flow\Fixtures\ClassWithSetters', $convertedChildProperties, $configuration);
        $this->assertEquals($expectedObject, $result);
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Property\Exception\InvalidTargetException
     */
    public function convertFromShouldThrowExceptionIfPropertyOnTargetObjectCouldNotBeSet()
    {
        $source = array(
            'propertyX' => 'bar'
        );
        $object = new ClassWithSetters();
        $convertedChildProperties = array(
            'propertyNotExisting' => 'bar'
        );

        $this->mockReflectionService->expects($this->once())->method('hasMethod')->with('TYPO3\Flow\Fixtures\ClassWithSetters', '__construct')->will($this->returnValue(false));
        $this->mockObjectManager->expects($this->once())->method('getClassNameByObjectName')->with('TYPO3\Flow\Fixtures\ClassWithSetters')->will($this->returnValue('TYPO3\Flow\Fixtures\ClassWithSetters'));
        $configuration = $this->buildConfiguration(array(PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED => true));
        $result = $this->converter->convertFrom($source, 'TYPO3\Flow\Fixtures\ClassWithSetters', $convertedChildProperties, $configuration);
        $this->assertSame($object, $result);
    }

    /**
     * @test
     */
    public function convertFromShouldCreateObjectWhenThereAreConstructorParameters()
    {
        $source = array(
            'propertyX' => 'bar'
        );
        $convertedChildProperties = array(
            'property1' => 'param1',
            'property2' => 'bar'
        );
        $expectedObject = new ClassWithSettersAndConstructor('param1');
        $expectedObject->setProperty2('bar');

        $this->mockReflectionService->expects($this->once())->method('hasMethod')->with('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor', '__construct')->will($this->returnValue(true));
        $this->mockReflectionService->expects($this->once())->method('getMethodParameters')->with('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor', '__construct')->will($this->returnValue(array(
            'property1' => array('optional' => false)
        )));
        $this->mockObjectManager->expects($this->once())->method('getClassNameByObjectName')->with('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor')->will($this->returnValue('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor'));
        $configuration = $this->buildConfiguration(array(PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED => true));
        $result = $this->converter->convertFrom($source, 'TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor', $convertedChildProperties, $configuration);
        $this->assertEquals($expectedObject, $result);
        $this->assertEquals('bar', $expectedObject->getProperty2());
    }

    /**
     * @test
     */
    public function convertFromShouldCreateObjectWhenThereAreOptionalConstructorParameters()
    {
        $source = array(
            'propertyX' => 'bar'
        );
        $expectedObject = new ClassWithSettersAndConstructor('thisIsTheDefaultValue');

        $this->mockReflectionService->expects($this->once())->method('hasMethod')->with('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor', '__construct')->will($this->returnValue(true));
        $this->mockReflectionService->expects($this->once())->method('getMethodParameters')->with('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor', '__construct')->will($this->returnValue(array(
            'property1' => array('optional' => true, 'defaultValue' => 'thisIsTheDefaultValue')
        )));
        $this->mockObjectManager->expects($this->once())->method('getClassNameByObjectName')->with('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor')->will($this->returnValue('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor'));
        $configuration = $this->buildConfiguration(array(PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED => true));
        $result = $this->converter->convertFrom($source, 'TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor', array(), $configuration);
        $this->assertEquals($expectedObject, $result);
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Property\Exception\InvalidTargetException
     */
    public function convertFromShouldThrowExceptionIfRequiredConstructorParameterWasNotFound()
    {
        $source = array(
            'propertyX' => 'bar'
        );
        $object = new ClassWithSettersAndConstructor('param1');
        $convertedChildProperties = array(
            'property2' => 'bar'
        );

        $this->mockReflectionService->expects($this->once())->method('hasMethod')->with('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor', '__construct')->will($this->returnValue(true));
        $this->mockReflectionService->expects($this->once())->method('getMethodParameters')->with('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor', '__construct')->will($this->returnValue(array(
            'property1' => array('optional' => false)
        )));
        $this->mockObjectManager->expects($this->once())->method('getClassNameByObjectName')->with('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor')->will($this->returnValue('TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor'));
        $configuration = $this->buildConfiguration(array(PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED => true));
        $result = $this->converter->convertFrom($source, 'TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor', $convertedChildProperties, $configuration);
        $this->assertSame($object, $result);
    }

    /**
     * @test
     */
    public function convertFromShouldReturnNullForEmptyString()
    {
        $source = '';
        $result = $this->converter->convertFrom($source, 'TYPO3\Flow\Fixtures\ClassWithSettersAndConstructor');
        $this->assertNull($result);
    }
}
