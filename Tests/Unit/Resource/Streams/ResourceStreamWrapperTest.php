<?php
namespace TYPO3\Flow\Tests\Unit\Resource\Streams;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use org\bovigo\vfs\vfsStream;
use TYPO3\Flow\Package\PackageManagerInterface;
use TYPO3\Flow\Resource\ResourceManager;
use TYPO3\Flow\Resource\Streams\ResourceStreamWrapper;
use TYPO3\Flow\Tests\UnitTestCase;

/**
 * Tests for the ResourceStreamWrapper class
 */
class ResourceStreamWrapperTest extends UnitTestCase
{
    /**
     * @var ResourceStreamWrapper
     */
    protected $resourceStreamWrapper;

    /**
     * @var PackageManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockPackageManager;

    /**
     * @var ResourceManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockResourceManager;

    public function setUp()
    {
        vfsStream::setup('Foo');

        $this->resourceStreamWrapper = new ResourceStreamWrapper();

        $this->mockPackageManager = $this->getMockBuilder('TYPO3\Flow\Package\PackageManagerInterface')->getMock();
        $this->inject($this->resourceStreamWrapper, 'packageManager', $this->mockPackageManager);

        $this->mockResourceManager = $this->getMockBuilder('TYPO3\Flow\Resource\ResourceManager')->disableOriginalConstructor()->getMock();
        $this->inject($this->resourceStreamWrapper, 'resourceManager', $this->mockResourceManager);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function openThrowsExceptionForInvalidScheme()
    {
        $openedPathAndFilename = '';
        $this->resourceStreamWrapper->open('invalid-scheme://foo/bar', 'r', 0, $openedPathAndFilename);
    }

    /**
     * @test
     */
    public function openResolvesALowerCaseSha1HashUsingTheResourceManager()
    {
        $sha1Hash = '68ac906495480a3404beee4874ed853a037a7a8f';
        $persistentResourcesStorageBaseUri = 'vfs://Foo/Some/';
        $absoluteResourcePath = $persistentResourcesStorageBaseUri . $sha1Hash;
        mkdir($persistentResourcesStorageBaseUri);
        file_put_contents('vfs://Foo/Some/' . $sha1Hash, 'fixture');

        $this->mockResourceManager->expects($this->once())->method('getPersistentResourcesStorageBaseUri')->will($this->returnValue($persistentResourcesStorageBaseUri));

        $openedPathAndFilename = '';
        $this->assertTrue($this->resourceStreamWrapper->open('resource://' . $sha1Hash, 'r', 0, $openedPathAndFilename));
        $this->assertSame($absoluteResourcePath, $openedPathAndFilename);
    }

    /**
     * @test
     */
    public function openResolvesAnUpperCaseSha1HashUsingTheResourceManager()
    {
        $sha1Hash = '68AC906495480A3404BEEE4874ED853A037A7A8F';
        $persistentResourcesStorageBaseUri = 'vfs://Foo/Some/';
        $absoluteResourcePath = $persistentResourcesStorageBaseUri . $sha1Hash;
        mkdir($persistentResourcesStorageBaseUri);
        file_put_contents('vfs://Foo/Some/' . $sha1Hash, 'fixture');

        $this->mockResourceManager->expects($this->once())->method('getPersistentResourcesStorageBaseUri')->will($this->returnValue($persistentResourcesStorageBaseUri));

        $openedPathAndFilename = '';
        $this->assertTrue($this->resourceStreamWrapper->open('resource://' . $sha1Hash, 'r', 0, $openedPathAndFilename));
        $this->assertSame($absoluteResourcePath, $openedPathAndFilename);
    }

    /**
     * @test
     * @expectedException \TYPO3\Flow\Resource\Exception
     */
    public function openThrowsExceptionForNonExistingPackages()
    {
        $packageKey = 'Non.Existing.Package';

        $openedPathAndFilename = '';
        $this->resourceStreamWrapper->open('resource://' . $packageKey . '/Some/Path', 'r', 0, $openedPathAndFilename);
    }

    /**
     * @test
     */
    public function openResolvesPackageKeysUsingThePackageManager()
    {
        $packageKey = 'Some.Package';
        mkdir('vfs://Foo/Some/');
        file_put_contents('vfs://Foo/Some/Path', 'fixture');

        $this->mockPackageManager->expects($this->once())->method('isPackageAvailable')->with($packageKey)->will($this->returnValue(true));

        $mockPackage = $this->getMockBuilder('TYPO3\Flow\Package\PackageInterface')->getMock();
        $mockPackage->expects($this->any())->method('getResourcesPath')->will($this->returnValue('vfs://Foo'));
        $this->mockPackageManager->expects($this->once())->method('getPackage')->with($packageKey)->will($this->returnValue($mockPackage));

        $openedPathAndFilename = '';
        $this->assertTrue($this->resourceStreamWrapper->open('resource://' . $packageKey . '/Some/Path', 'r', 0, $openedPathAndFilename));
    }

    /**
     * @test
     */
    public function openResolves40CharacterLongPackageKeysUsingThePackageManager()
    {
        $packageKey = 'Some.PackageKey.Containing.40.Characters';
        mkdir('vfs://Foo/Some/');
        file_put_contents('vfs://Foo/Some/Path', 'fixture');

        $this->mockPackageManager->expects($this->once())->method('isPackageAvailable')->with($packageKey)->will($this->returnValue(true));

        $mockPackage = $this->getMockBuilder('TYPO3\Flow\Package\PackageInterface')->getMock();
        $mockPackage->expects($this->any())->method('getResourcesPath')->will($this->returnValue('vfs://Foo'));
        $this->mockPackageManager->expects($this->once())->method('getPackage')->with($packageKey)->will($this->returnValue($mockPackage));

        $openedPathAndFilename = '';
        $this->assertTrue($this->resourceStreamWrapper->open('resource://' . $packageKey . '/Some/Path', 'r', 0, $openedPathAndFilename));
    }
}
