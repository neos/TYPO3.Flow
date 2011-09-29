<?php
namespace TYPO3\FLOW3\Tests\Functional\Object\Fixtures;

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
 * A class of scope prototype
 *
 * @scope prototype
 * @foo test annotation
 * @bar
 * @entity
 */
class PrototypeClassA implements PrototypeClassAishInterface {

	/**
	 * @var \TYPO3\FLOW3\Tests\Functional\Object\Fixtures\SingletonClassA
	 */
	protected $singletonA;

	/**
	 * @var string
	 */
	protected $someProperty;

	/**
	 * @param \TYPO3\FLOW3\Tests\Functional\Object\Fixtures\SingletonClassA $singletonA
	 * @return void
	 */
	public function injectSingletonA(\TYPO3\FLOW3\Tests\Functional\Object\Fixtures\SingletonClassA $singletonA) {
		$this->singletonA = $singletonA;
	}

	/**
	 * @return \TYPO3\FLOW3\Tests\Functional\Object\Fixtures\SingletonClassA
	 */
	public function getSingletonA() {
		return $this->singletonA;
	}

	/**
	 * @param string $someProperty
	 * @return void
	 * @bar
	 */
	public function setSomeProperty($someProperty) {
		$this->someProperty = $someProperty;
	}

	/**
	 * @return string
	 */
	public function getSomeProperty() {
		return $this->someProperty;
	}
}
?>