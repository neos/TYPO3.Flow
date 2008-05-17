<?php
declare(ENCODING = 'utf-8');

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 2 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */

/**
 * @package FLOW3
 * @subpackage Tests
 * @version $Id$
 */

/**
 * Testcase for Reflection Property
 *
 * @package FLOW3
 * @subpackage Tests
 * @version $Id:F3_FLOW3_AOP_Framework.php 201 2007-03-30 11:18:30Z robert $
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class F3_FLOW3_Reflection_PropertyTest extends F3_Testing_BaseTestCase {

	/**
	 * @var string
	 */
	public $publicProperty = 'I\'m public';

	/**
	 * @var string
	 */
	protected $protectedProperty = 'abc';

	/**
	 * @var string
	 */
	private $privateProperty = '123';

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getValueThrowsAnExceptionOnReflectingANonObject() {
		$reflectionProperty = new F3_FLOW3_Reflection_Property(__CLASS__, 'protectedProperty');
		try {
			$reflectionProperty->getValue(__CLASS__);
			$this->fail('getValue($this) did not throw an exception although only the specified class was not an object.');
		} catch (F3_FLOW3_Reflection_Exception $exception) {

		}
	}

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getValueReturnsValueOfAPublicProperty() {
		$reflectionProperty = new F3_FLOW3_Reflection_Property(__CLASS__, 'publicProperty');
		$this->assertEquals('I\'m public', $reflectionProperty->getValue($this), 'ReflectionProperty->getValue($this) did not return the value of a public property.');
	}

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getValueEvenReturnsValueOfAProtectedProperty() {
		$reflectionProperty = new F3_FLOW3_Reflection_Property(__CLASS__, 'protectedProperty');
		$this->assertEquals('abc', $reflectionProperty->getValue($this), 'ReflectionProperty->getValue($this) did not return the value of a protected property.');

		$this->protectedProperty = 'def';
		$this->assertEquals('def', $reflectionProperty->getValue($this), 'ReflectionProperty->getValue($this) did not return "def".');
	}

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getValueReturnsValueOfAProtectedPropertyEvenIfItIsAnObject() {
		$reflectionProperty = new F3_FLOW3_Reflection_Property(__CLASS__, 'protectedProperty');
		$this->protectedProperty = new ArrayObject(array('1', '2', '3'));
		$this->assertEquals($this->protectedProperty, $reflectionProperty->getValue($this), 'ReflectionProperty->getValue($this) did not return the object of our protected property.');

		$this->protectedProperty = $this;
		$this->assertSame($this, $reflectionProperty->getValue($this), 'ReflectionProperty->getValue($this) did not return the reference to $this.');
	}

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function setValueEvenSetsValueOfAPublicProperty() {
		$reflectionProperty = new F3_FLOW3_Reflection_Property(__CLASS__, 'publicProperty');
		$reflectionProperty->setValue($this, 'modified');
		$this->assertEquals('modified', $this->publicProperty, 'ReflectionProperty->setValue() did not successfully set the value of a public property.');
	}

}
?>