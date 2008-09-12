<?php
declare(ENCODING = 'utf-8');
namespace F3::FLOW3::Security::Authorization::Interceptor;

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
 * @version $Id:$
 */

/**
 * Testcase for the authentication required security interceptor
 *
 * @package FLOW3
 * @subpackage Tests
 * @version $Id:$
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class RequireAuthenticationTest extends F3::Testing::BaseTestCase {

	/**
	 * @test
	 * @category unit
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function allTokensFromTheContextAreGivenToTheAuthenticationManagerForAuthentication() {
		$contextHolder = $this->getMock('F3::FLOW3::Security::ContextHolderInterface');
		$context = $this->getMock('F3::FLOW3::Security::Context', array(), array(), '', FALSE);
		$authenticationManager = $this->getMock('F3::FLOW3::Security::Authentication::ManagerInterface');

		$token1 = $this->getMock('F3::FLOW3::Security::Authentication::TokenInterface', array(), array(), 'tokenToAuthenticate1');
		$token2 = $this->getMock('F3::FLOW3::Security::Authentication::TokenInterface', array(), array(), 'tokenToAuthenticate2');

		$contextHolder->expects($this->once())->method('getContext')->will($this->returnValue($context));
		$context->expects($this->once())->method('getAuthenticationTokens')->will($this->returnValue(array($token1, $token2)));
		$authenticationManager->expects($this->at(0))->method('authenticate')->with($token1);
		$authenticationManager->expects($this->at(1))->method('authenticate')->with($token2);

		$interceptor = new F3::FLOW3::Security::Authorization::Interceptor::RequireAuthentication($contextHolder, $authenticationManager);
		$interceptor->invoke();
	}
}
?>