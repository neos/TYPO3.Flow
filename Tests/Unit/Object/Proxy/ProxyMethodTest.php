<?php
namespace TYPO3\FLOW3\Tests\Unit\Object\Proxy;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 *
 */
class ProxyMethodTest extends \TYPO3\FLOW3\Tests\UnitTestCase {

	/**
	 * @test
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 */
	public function buildMethodDocumentationShouldAddAllAnnotations() {

		$mockReflectionService = $this->getMock('TYPO3\FLOW3\Reflection\ReflectionService', array(), array(), '', FALSE);
		$mockReflectionService->expects($this->any())->method('hasMethod')->will($this->returnValue(TRUE));
		$mockReflectionService->expects($this->any())->method('getIgnoredTags')->will($this->returnValue(array('return')));
		$mockReflectionService->expects($this->any())->method('getMethodTagsValues')->with('My\Class\Name', 'myMethod')->will($this->returnValue(array(
			'param' => array(
				'string $name'
			),
			'return' => array(
				'void'
			),
			'validate' => array(
				'foo1 bar1',
				'foo2 bar2'
			),
			'skipCsrf' => array()
		)));



		$mockProxyMethod = $this->getAccessibleMock('TYPO3\FLOW3\Object\Proxy\ProxyMethod', array('dummy'), array(), '', FALSE);
		$mockProxyMethod->injectReflectionService($mockReflectionService);
		$methodDocumentation = $mockProxyMethod->_call('buildMethodDocumentation', 'My\Class\Name', 'myMethod');

		$expected =
			'	/**' . chr(10) .
			'	 * Autogenerated Proxy Method' . chr(10) .
			'	 * @param string $name' . chr(10) .
			'	 * @validate foo1 bar1' . chr(10) .
			'	 * @validate foo2 bar2' . chr(10) .
			'	 * @skipCsrf' . chr(10) .
			'	 */' . chr(10);
		$this->assertEquals($expected, $methodDocumentation);
	}
}

?>