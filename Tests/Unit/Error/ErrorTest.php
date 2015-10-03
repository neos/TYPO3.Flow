<?php
namespace TYPO3\Flow\Tests\Unit\Error;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Testcase for the Error object
 *
 */
class ErrorTest extends \TYPO3\Flow\Tests\UnitTestCase
{
    /**
     * @test
     */
    public function theConstructorSetsTheErrorMessageCorrectly()
    {
        $errorMessage = 'The message';
        $error = new \TYPO3\Flow\Error\Error($errorMessage, 0);

        $this->assertEquals($errorMessage, $error->getMessage());
    }

    /**
     * @test
     */
    public function theConstructorSetsTheErrorCodeCorrectly()
    {
        $errorCode = 123456789;
        $error = new \TYPO3\Flow\Error\Error('', $errorCode);

        $this->assertEquals($errorCode, $error->getCode());
    }
}
