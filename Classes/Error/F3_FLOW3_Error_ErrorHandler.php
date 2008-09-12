<?php
declare(ENCODING = 'utf-8');
namespace F3::FLOW3::Error;

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
 * @subpackage Error
 * @version $Id$
 */

/**
 * Global error handler for FLOW3
 *
 * @package FLOW3
 * @subpackage Error
 * @version $Id$
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class ErrorHandler {

	/**
	 * @var array
	 */
	protected $exceptionalErrors = array();

	/**
	 * Constructs this error handler - registers itself as the default error handler.
	 *
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function __construct() {
		set_error_handler(array($this, 'handleError'));
	}

	/**
	 * Defines which error levels result should result in an exception thrown.
	 *
	 * @param array $exceptionalErros An array of E_* error levels
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function setExceptionalErrors(array $exceptionalErrors) {
		$this->exceptionalErrors = $exceptionalErrors;
	}

	/**
	 * Handles an error by converting it into an exception
	 *
	 * @param integer $errorLevel: The error level - one of the E_* constants
	 * @param string $errorMessage: The error message
	 * @param string $errorFile: Name of the file the error occurred in
	 * @param integer $errorLine: Line number where the error occurred
	 * @return void
	 * @throws F3::FLOW3::Error::Exception with the data passed to this method
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function handleError($errorLevel, $errorMessage, $errorFile, $errorLine) {
		$errorLevels = array (
			E_ERROR              => 'Error',
			E_WARNING            => 'Warning',
			E_PARSE              => 'Parsing Error',
			E_NOTICE             => 'Notice',
			E_CORE_ERROR         => 'Core Error',
			E_CORE_WARNING       => 'Core Warning',
			E_COMPILE_ERROR      => 'Compile Error',
			E_COMPILE_WARNING    => 'Compile Warning',
			E_USER_ERROR         => 'User Error',
			E_USER_WARNING       => 'User Warning',
			E_USER_NOTICE        => 'User Notice',
			E_STRICT             => 'Runtime Notice',
			E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
		);
		if (in_array($errorLevel, $this->exceptionalErrors)) {
			throw new F3::FLOW3::Error::Exception($errorLevels[$errorLevel] . ': ' . $errorMessage . ' in ' . $errorFile . ' line ' . $errorLine, 1);
		}
	}
}

?>