<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\MVC\Web\Routing;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * @package FLOW3
 * @subpackage Tests
 * @version $Id$
 */

require_once(__DIR__ . '/../../Fixture/Web/Routing/MockRoutePartHandler.php');

/**
 * Testcase for the MVC Web Routing Route Class
 *
 * @package FLOW3
 * @subpackage Tests
 * @version $Id$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class RouteTest extends \F3\Testing\BaseTestCase {

	/*                                                                        *
	 * Basic functionality (scope, getters, setters, exceptions)              *
	 *                                                                        */

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeIsPrototype() {
		$route1 = $this->objectFactory->create('F3\FLOW3\MVC\Web\Routing\Route');
		$route2 = $this->objectFactory->create('F3\FLOW3\MVC\Web\Routing\Route');
		$this->assertNotSame($route1, $route2, 'Obviously route is not prototype!');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function setNameCorrectlySetsRouteName() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setName('SomeName');

		$this->assertEquals('SomeName', $route->getName());
	}

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function theControllerObjectNamePatternCanBeSetAndRetrieved() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setControllerObjectNamePattern('XY3_@package_@controller');
		$this->assertEquals('XY3_@package_@controller', $route->getControllerObjectNamePattern());
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function settingUriPatternResetsRoute() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}/foo/{key2}/bar');

		$this->assertFalse($route->matches('value1/foo/value2/foo'), '"{key1}/foo/{key2}/bar"-Route should not match "value1/foo/value2/foo"-request.');
		$this->assertTrue($route->matches('value1/foo/value2/bar'), '"{key1}/foo/{key2}/bar"-Route should match "value1/foo/value2/bar"-request.');
		$this->assertEquals(array('key1' => 'value1', 'key2' => 'value2'), $route->getMatchResults(), 'Route match results should be set correctly on successful match');

		$route->setUriPattern('foo/{key3}/foo');

		$this->assertFalse($route->matches('foo/value3/bar'), '"foo/{key3}/foo"-Route should not match "foo/value3/bar"-request.');
		$this->assertTrue($route->matches('foo/value3/foo'), '"foo/{key3}/foo"-Route should match "foo/value3/foo"-request.');
		$this->assertEquals(array('key3' => 'value3'), $route->getMatchResults(), 'Route match results should be set correctly on successful match');
	}

	/**
	 * @test
	 * @expectedException \F3\FLOW3\Object\Exception\UnknownObject
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function settingNonExistingRoutePartHandlerThrowsException() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}/{key2}');
		$route->setRoutePartHandlers(
			array(
				'key1' => 'Non_Existing_RoutePartHandler',
			)
		);
		$route->parse();
	}

	/**
	 * @test
	 * @expectedException \F3\FLOW3\MVC\Exception\InvalidRoutePartHandler
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function settingInvalidRoutePartHandlerThrowsException() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}/{key2}');
		$route->setRoutePartHandlers(
			array(
				'key1' => 'F3\FLOW3\MVC\Web\Routing\StaticRoutePart',
			)
		);
		$route->parse();
	}

	/**
	 * @test
	 * @expectedException \F3\FLOW3\MVC\Exception\InvalidUriPattern
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function uriPatternWithSuccessiveDynamicRoutepartsThrowsException() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}{key2}');
		$route->parse();
	}

	/**
	 * @test
	 * @expectedException \F3\FLOW3\MVC\Exception\InvalidUriPattern
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function uriPatternWithSuccessiveOptionalSectionsThrowsException() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('(foo/bar)(/bar/foo)');
		$route->parse();
	}

	/**
	 * @test
	 * @expectedException \F3\FLOW3\MVC\Exception\InvalidUriPattern
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function uriPatternWithUnterminatedOptionalSectionsThrowsException() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('foo/(bar');
		$route->parse();
	}

	/**
	 * @test
	 * @expectedException \F3\FLOW3\MVC\Exception\InvalidUriPattern
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function uriPatternWithUnopenedOptionalSectionsThrowsException() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('foo)/bar');
		$route->parse();
	}

	/*                                                                        *
	 * URI matching                                                           *
	 *                                                                        */

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchIfRequestPathIsNull() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('');

		$this->assertFalse($route->matches(NULL), 'Route should not match if requestPath is NULL.');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchEmptyRequestPathIfUriPatternIsNotSet() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);

		$this->assertFalse($route->matches(''), 'Route should not match if no URI Pattern is set.');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchIfRequestPathIsDifferentFromStaticUriPattern() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('foo/bar');

		$this->assertFalse($route->matches('bar/foo'), '"foo/bar"-Route should not match "bar/foo"-request.');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchIfOneSegmentOfRequestPathIsDifferentFromItsRespectiveStaticUriPatternSegment() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('foo/{bar}');

		$this->assertFalse($route->matches('bar/someValue'), '"foo/{bar}"-Route should not match "bar/someValue"-request.');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesEmptyRequestPathIfUriPatternIsEmpty() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('');

		$this->assertTrue($route->matches(''), 'Route should match if URI Pattern and RequestPath are empty.');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesIfRequestPathIsEqualToStaticUriPattern() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('foo/bar');

		$this->assertTrue($route->matches('foo/bar'), '"foo/bar"-Route should match "foo/bar"-request.');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchIfRequestPathIsEqualToStaticUriPatternWithoutSlashes() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('required1/required2');

		$this->assertFalse($route->matches('required1required2'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesIfStaticSegmentsMatchAndASegmentExistsForAllDynamicUriPartSegments() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('foo/{bar}');

		$this->assertTrue($route->matches('foo/someValue'), '"foo/{bar}"-Route should match "foo/someValue"-request.');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function getMatchResultsReturnsCorrectResultsAfterSuccessfulMatch() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('foo/{bar}');
		$route->matches('foo/someValue');

		$this->assertEquals(array('bar' => 'someValue'), $route->getMatchResults(), 'Route match results should be set correctly on successful match');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function staticAndDynamicRoutesCanBeMixedInAnyOrder() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}/foo/{key2}/bar');

		$this->assertFalse($route->matches('value1/foo/value2/foo'), '"{key1}/foo/{key2}/bar"-Route should not match "value1/foo/value2/foo"-request.');
		$this->assertTrue($route->matches('value1/foo/value2/bar'), '"{key1}/foo/{key2}/bar"-Route should match "value1/foo/value2/bar"-request.');
		$this->assertEquals(array('key1' => 'value1', 'key2' => 'value2'), $route->getMatchResults(), 'Route match results should be set correctly on successful match');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function uriPatternSegmentCanContainTwoDynamicRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('user/{firstName}-{lastName}');

		$this->assertFalse($route->matches('user/johndoe'), '"user/{firstName}-{lastName}"-Route should not match "user/johndoe"-request.');
		$this->assertTrue($route->matches('user/john-doe'), '"user/{firstName}-{lastName}"-Route should match "user/john-doe"-request.');
		$this->assertEquals(array('firstName' => 'john', 'lastName' => 'doe'), $route->getMatchResults(), 'Route match results should be set correctly on successful match');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function uriPatternSegmentsCanContainMultipleDynamicRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}-{key2}/{key3}.{key4}.{@format}');

		$this->assertFalse($route->matches('value1-value2/value3.value4value5'), '"{key1}-{key2}/{key3}.{key4}.{@format}"-Route should not match "value1-value2/value3.value4value5"-request.');
		$this->assertTrue($route->matches('value1-value2/value3.value4.value5'), '"{key1}-{key2}/{key3}.{key4}.{@format}"-Route should match "value1-value2/value3.value4.value5"-request.');
		$this->assertEquals(array('key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3', 'key4' => 'value4', '@format' => 'value5'), $route->getMatchResults(), 'Route match results should be set correctly on successful match');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchIfRoutePartDoesNotMatchAndDefaultValueIsSet() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{foo}');
		$route->setDefaults(array('foo' => 'bar'));

		$this->assertFalse($route->matches(''), 'Route should not match if required Route Part does not match.');
	}

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function setDefaultsAllowsToSetTheDefaultPackageControllerAndActionName() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('SomePackage');

		$defaults = array(
			'@package' => 'SomePackage',
			'@controller' => 'SomeController',
			'@action' => 'someAction'
		);

		$route->setDefaults($defaults);
		$route->matches('SomePackage');
		$matchResults = $route->getMatchResults();

		$this->assertEquals($defaults['@controller'], $matchResults{'@controller'});
		$this->assertEquals($defaults['@action'], $matchResults['@action']);
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function registeredRoutePartHandlerIsInvokedWhenCallingMatch() {
		$this->objectManager->registerObject('F3\FLOW3\MVC\Fixture\Web\Routing\MockRoutePartHandler');
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}/{key2}');
		$route->setRoutePartHandlers(
			array(
				'key1' => 'F3\FLOW3\MVC\Fixture\Web\Routing\MockRoutePartHandler',
			)
		);
		$route->matches('foo/bar');

		$this->assertEquals(array('key1' => '_match_invoked_', 'key2' => 'bar'), $route->getMatchResults());
	}

	/*                                                                        *
	 * URI matching (optional Route Parts)                                    *
	 *                                                                        */

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesEmptyRequestPathIfUriPatternContainsOneOptionalStaticRoutePart() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('(optional)');

		$this->assertTrue($route->matches(''));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathWithAllPartsIfUriPatternContainsOneOptionalAndOneRequiredStaticRoutePart() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('required(optional)');

		$this->assertTrue($route->matches('requiredoptional'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathWithOnlyRequiredPartsIfUriPatternContainsOneRequiredAndOneOptionalStaticRoutePart() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('required(optional)');

		$this->assertTrue($route->matches('required'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathWithOnlyRequiredPartsIfUriPatternContainsOneOptionalAndOneRequiredStaticRoutePart() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('(optional)required');

		$this->assertTrue($route->matches('required'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathWithOnlyRequiredPartsIfUriPatternContainsTwoOptionalAndOneRequiredStaticRoutePart() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('(optional)required(optional2)');

		$this->assertTrue($route->matches('required'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathWithAllPartsIfUriPatternContainsTwoOptionalAndOneRequiredStaticRoutePart() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('(optional)required(optional2)');

		$this->assertTrue($route->matches('optionalrequiredoptional2'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchEmptyRequestPathIfUriPatternContainsOneOptionalDynamicRoutePartWithoutDefaultValue() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('({optional})');

		$this->assertFalse($route->matches(''));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesEmptyRequestPathIfUriPatternContainsOneOptionalDynamicRoutePartWithDefaultValue() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('({optional})');
		$route->setDefaults(array('optional' => 'defaultValue'));

		$this->assertTrue($route->matches(''));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchRequestPathContainingNoneOfTheOptionalRoutePartsIfNoDefaultsAreSet() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('page(.{@format})');

		$this->assertFalse($route->matches('page'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchRequestPathContainingOnlySomeOfTheOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('page(.{@format})');
		$route->setDefaults(array('@format' => 'html'));

		$this->assertFalse($route->matches('page.'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathContainingNoneOfTheOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('page(.{@format})');
		$route->setDefaults(array('@format' => 'html'));

		$this->assertTrue($route->matches('page'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathContainingAllOfTheOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('page(.{@format})');
		$route->setDefaults(array('@format' => 'html'));

		$this->assertTrue($route->matches('page.html'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathWithOnlyRequiredPartsIfUriPatternEndsWithTwoSuccessiveOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('required(/optional1/optional2)');

		$this->assertTrue($route->matches('required'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchRequestPathWithRequiredAndOnlyOneOptionalPartsIfUriPatternEndsWithTwoSuccessiveOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('required(/optional1/optional2)');

		$this->assertFalse($route->matches('required/optional1'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchRequestPathWithAllPartsIfUriPatternEndsWithTwoSuccessiveOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('required(/optional1/optional2)');

		$this->assertTrue($route->matches('required/optional1/optional2'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathWithOnlyRequiredPartsIfUriPatternContainsTwoSuccessiveOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('required1(/optional1/optional2)/required2');

		$this->assertTrue($route->matches('required1/required2'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchRequestPathWithOnlyOneOptionalPartIfUriPatternContainsTwoSuccessiveOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('required1/(optional1/optional2/)required2');

		$this->assertFalse($route->matches('required1/optional1/required2'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathWithAllPartsIfUriPatternContainsTwoSuccessiveOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('required1/(optional1/optional2/)required2');

		$this->assertTrue($route->matches('required1/optional1/optional2/required2'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathWithOnlyRequiredPartsIfUriPatternStartsWithTwoSuccessiveOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('(optional1/optional2/)required1/required2');

		$this->assertTrue($route->matches('required1/required2'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchRequestPathWithOnlyOneOptionalPartIfUriPatternStartsWithTwoSuccessiveOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('(optional1/optional2/)required1/required2');

		$this->assertFalse($route->matches('optional1/required1/required2'));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesRequestPathWithAllPartsIfUriPatternStartsWithTwoSuccessiveOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('(optional1/optional2/)required1/required2');

		$this->assertTrue($route->matches('optional1/optional2/required1/required2'));
	}
	

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeDoesNotMatchIfRoutePartDoesNotMatchAndIsOptionalButHasNoDefault() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('({foo})');

		$this->assertFalse($route->matches(''), 'Route should not match if optional Route Part does not match and has no default value.');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeMatchesIfRoutePartDoesNotMatchButIsOptionalAndHasDefault() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('({foo})');
		$route->setDefaults(array('foo' => 'bar'));

		$this->assertTrue($route->matches(''), 'Route should match if optional Route Part has a default value.');
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function defaultValuesAreSetForUriPatternSegmentsWithMultipleOptionalRouteParts() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}-({key2})/({key3}).({key4}.{@format})');
		$defaults = array(
			'key1' => 'defaultValue1',
			'key2' => 'defaultValue2',
			'key3' => 'defaultValue3',
			'key4' => 'defaultValue4'
		);
		$route->setDefaults($defaults);
		$route->matches('foo-/.bar.xml');

		$this->assertEquals(array('key1' => 'foo', 'key2' => 'defaultValue2', 'key3' => 'defaultValue3', 'key4' => 'bar', '@format' => 'xml'), $route->getMatchResults(), 'Route match results should be set correctly on successful match');
	}

	/*                                                                        *
	 * URI resolving                                                          *
	 *                                                                        */

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */

	public function matchingRouteIsProperlyResolved() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}-{key2}/{key3}.{key4}.{@format}');
		$route->setDefaults(array('@format' => 'xml'));
		$routeValues = array('key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3', 'key4' => 'value4');

		$this->assertTrue($route->resolves($routeValues));
		$this->assertEquals('value1-value2/value3.value4.xml', $route->getMatchingURI());
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeCantBeResolvedIfUriPatternContainsLessValuesThanAreSpecified() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}-{key2}/{key3}.{key4}.{@format}');
		$route->setDefaults(array('@format' => 'xml'));
		$routeValues = array('key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3', 'key4' => 'value4', 'nonexistingkey' => 'foo');

		$this->assertFalse($route->resolves($routeValues));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeCanBeResolvedIfASpecifiedValueIsEqualToItsDefaultValue() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('');
		$route->setDefaults(array('key1' => 'value1', 'key2' => 'value2'));
		$routeValues = array('key1' => 'value1');

		$this->assertTrue($route->resolves($routeValues));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function routeCantBeResolvedIfASpecifiedValueIsNotEqualToItsDefaultValue() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('');
		$route->setDefaults(array('key1' => 'value1', 'key2' => 'value2'));
		$routeValues = array('key2' => 'differentValue');

		$this->assertFalse($route->resolves($routeValues));
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function matchingRequestPathIsNullAfterUnsuccessfulResolve() {
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}');
		$routeValues = array('key1' => 'value1');

		$this->assertTrue($route->resolves($routeValues));

		$routeValues = array('differentKey' => 'value1');
		$this->assertFalse($route->resolves($routeValues));
		$this->assertNull($route->getMatchingURI());
	}

	/**
	 * @test
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function registeredRoutePartHandlerIsInvokedWhenCallingResolve() {
		$this->objectManager->registerObject('F3\FLOW3\MVC\Fixture\Web\Routing\MockRoutePartHandler');
		$route = new \F3\FLOW3\MVC\Web\Routing\Route($this->objectFactory, $this->objectManager);
		$route->setUriPattern('{key1}/{key2}');
		$route->setRoutePartHandlers(
			array(
				'key1' => 'F3\FLOW3\MVC\Fixture\Web\Routing\MockRoutePartHandler',
			)
		);
		$routeValues = array('key2' => 'value2');
		$route->resolves($routeValues);

		$this->assertEquals('_resolve_invoked_/value2', $route->getMatchingURI());
	}
}
?>
