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
 * @subpackage Error
 * @version $Id$
 */

/**
 * A basic but solid exception handler which catches everything which
 * falls through the other exception handlers and provides useful debugging
 * information.
 *
 * @package FLOW3
 * @subpackage Error
 * @version $Id$
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class F3_FLOW3_Error_DebugExceptionHandler implements F3_FLOW3_Error_ExceptionHandlerInterface {

	/**
	 * Constructs this exception handler - registers itself as the default exception handler.
	 *
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function __construct() {
		@set_exception_handler(array($this, 'handleException'));
	}

	/**
	 * Displays the given exception
	 *
	 * @param Exception $exception: The exception object
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function handleException(Exception $exception) {
		switch (php_sapi_name()) {
			case 'cli' :
				$this->echoExceptionCLI($exception);
				break;
			default :
				$this->echoExceptionWeb($exception);
		}
	}

	/**
	 * Formats and echoes the exception as XHTML.
	 *
	 * @param  Exception $exception: The exception object
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function echoExceptionWeb(Exception $exception) {
		$pathPosition = strpos($exception->getFile(), FLOW3_PATH_PACKAGES);
		$filePathAndName = ($pathPosition === 0) ? substr($exception->getFile(), strlen(FLOW3_PATH_PACKAGES)) : $exception->getFile();

		$exceptionCodeNumber = ($exception->getCode() > 0) ? '#' . $exception->getCode() . ': ' : '';
		$moreInformationLink = ($exceptionCodeNumber != '') ? '(<a href="http://typo3.org/go/exception/' . $exception->getCode() . '">More information</a>)' : '';
		$codeSnippet = $this->getCodeSnippet($exception->getFile(), $exception->getLine());
		$backtraceCode = $this->getBacktraceCode($exception->getTrace());

		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN"
				"http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
			<head>
				<title>FLOW3 Exception</title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			</head>
			<style>
				.ExceptionProperty {
					color: #101010;
				}
				pre {
					margin: 0;
					font-size: 11px;
					color: #515151;
					background-color: #D0D0D0;
					padding-left: 30px;
				}
			</style>
			<div style="
					position: absolute;
					left: 10px;
					background-color: #B9B9B9;
					outline: 1px solid #515151;
					color: #515151;
					font-family: Arial, Helvetica, sans-serif;
					font-size: 12px;
					margin: 10px;
					padding: 0;
				">
				<div style="width: 100%; background-color: #515151; color: white; padding: 2px; margin: 0 0 6px 0;">Uncaught FLOW3 Exception</div>
				<div style="width: 100%; padding: 2px; margin: 0 0 6px 0;">
					<strong style="color: #BE0027;">' . $exceptionCodeNumber . $exception->getMessage() . '</strong> ' . $moreInformationLink . '<br />
					<br />
					<span class="ExceptionProperty">' . get_class($exception) . '</span> thrown in file<br />
					<span class="ExceptionProperty">' . $filePathAndName . '</span> in line
					<span class="ExceptionProperty">' . $exception->getLine() . '</span>.<br />
					<br />
					' . $backtraceCode . '
				</div>
		';
		echo '
			</div>
		';
	}

	/**
	 * Formats and echoes the exception for the command line
	 *
	 * @param Exception $exception: The exception object
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function echoExceptionCLI(Exception $exception) {
		$pathPosition = strpos($exception->getFile(), FLOW3_PATH_PACKAGES);
		$filePathAndName = ($pathPosition === 0) ? substr($exception->getFile(), strlen(FLOW3_PATH_PACKAGES)) : $exception->getFile();

		$exceptionCodeNumber = ($exception->getCode() > 0) ? '#' . $exception->getCode() . ': ' : '';

		echo "\nUncaught FLOW3 Exception " . $exceptionCodeNumber . $exception->getMessage() . "\n";
		echo "thrown in file " . $filePathAndName . "\n";
		echo "in line " . $exception->getLine() . "\n\n";
	}

	/**
	 * Renders some backtrace
	 *
	 * @param array $trace: The trace
	 * @return string Backtrace information
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function getBacktraceCode(array $trace) {
		$backtraceCode = '';
		if (count($trace)) {
			foreach ($trace as $index => $step) {
				if (isset($step['file'])) {
					$pathPosition = strpos($step['file'], FLOW3_PATH_PACKAGES);
					$stepFileName = ($pathPosition === 0) ? substr($step['file'], strlen(FLOW3_PATH_PACKAGES)) : $step['file'];
				} else {
					$stepFileName = '< unknown >';
				}
				$class = isset($step['class']) ? $step['class'] . '<span style="color:white;">::</span>' : '';

				$arguments = '';
				if (isset($step['args']) && is_array($step['args'])) {
					foreach ($step['args'] as $argument) {
						$arguments .= (strlen($arguments) == 0) ? '' : '<span style="color:white;">,</span> ';
						if (is_object($argument)) {
							$arguments .= '<span style="color:#FF8700;"><em>' . get_class($argument) . '</em></span>';
						} elseif (is_string($argument)) {
							$preparedArgument = (strlen($argument) < 40) ? $argument : substr($argument, 0, 20) . '…' . substr($argument, -20, 18);
							$preparedArgument = htmlspecialchars($preparedArgument);
							$preparedArgument = str_replace("\n", '<span style="color:white;">⏎</span>', $preparedArgument);
							$arguments .= '"<span style="color:#FF8700;">' . $preparedArgument . '</span>"';
						} elseif (is_numeric($argument)) {
							$arguments .= '<span style="color:#FF8700;">' . (string)$argument . '</span>';
						} else {
							$arguments .= '<span style="color:#FF8700;"><em>' . gettype($argument) . '</em></span>';
						}
					}
				}

				$backtraceCode .= '<pre style="color:#69A550; background-color: #414141; padding: 4px 2px 4px 2px;">';
				$backtraceCode .= '<span style="color:white;">' . (count($trace) - $index) . '</span> ' . $class . $step['function'] . '<span style="color:white;">(' . $arguments . ')</span>';
				$backtraceCode .= '</pre>';

				if (isset($step['file'])) {
					$backtraceCode .= $this->getCodeSnippet($step['file'], $step['line']) . '<br />';
				}
			}
		}

		return $backtraceCode;
	}

	/**
	 * Returns a code snippet from the specified file.
	 *
	 * @param string $filePathAndName: Absolute path and file name of the PHP file
	 * @param integer $lineNumber: Line number defining the center of the code snippet
	 * @return string The code snippet
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function getCodeSnippet($filePathAndName, $lineNumber) {
		$codeSnippet = '<br />';
		if (@file_exists($filePathAndName)) {
			$phpFile = @file($filePathAndName);
			if (is_array($phpFile)) {
				$startLine = ($lineNumber > 2) ? ($lineNumber - 2) : 1;
				$endLine = ($lineNumber < (count($phpFile) - 2)) ? ($lineNumber + 3) : count($phpFile) + 1;
				if ($endLine > $startLine) {
					$codeSnippet = '<br /><span style="font-size:10px;">' . $filePathAndName . ':</span><br /><pre>';
					for ($line = $startLine; $line < $endLine; $line++) {
						$codeLine = str_replace("\t", ' ', $phpFile[$line-1]);

						if ($line == $lineNumber) $codeSnippet .= '</pre><pre style="background-color: #F1F1F1; color: black;">';
						$codeSnippet .= sprintf('%05d', $line) . ': ' . $codeLine;
						if ($line == $lineNumber) $codeSnippet .= '</pre><pre>';
					}
					$codeSnippet .= '</pre>';
				}
			}
		}
		return $codeSnippet;
	}
}

?>