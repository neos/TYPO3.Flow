<?php
namespace TYPO3\Flow\Tests\Unit\Validation\Validator;

/*                                                                        *
 * This script belongs to the Flow framework.                             *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the MIT license.                                          *
 *                                                                        */

require_once('AbstractValidatorTestcase.php');

/**
 * Testcase for the integer validator
 *
 */
class IntegerValidatorTest extends \TYPO3\Flow\Tests\Unit\Validation\Validator\AbstractValidatorTestcase
{
    protected $validatorClassName = 'TYPO3\Flow\Validation\Validator\IntegerValidator';

    /**
     * @test
     */
    public function validateReturnsNoErrorIfTheGivenValueIsNull()
    {
        $this->assertFalse($this->validator->validate(null)->hasErrors());
    }

    /**
     * @test
     */
    public function validateReturnsNoErrorIfTheGivenValueIsAnEmptyString()
    {
        $this->assertFalse($this->validator->validate('')->hasErrors());
    }

    /**
     * Data provider with valid integers
     *
     * @return array
     */
    public function validIntegers()
    {
        return array(
            array(1029437),
            array('12345'),
            array('+12345'),
            array('-12345')
        );
    }

    /**
     * @test
     * @dataProvider validIntegers
     */
    public function integerValidatorReturnsNoErrorsForAValidInteger($integer)
    {
        $this->assertFalse($this->validator->validate($integer)->hasErrors());
    }

    /**
     * Data provider with invalid integers
     *
     * @return array
     */
    public function invalidIntegers()
    {
        return array(
            array('not a number'),
            array(3.1415),
            array('12345.987')
        );
    }

    /**
     * @test
     * @dataProvider invalidIntegers
     */
    public function integerValidatorReturnsErrorForAnInvalidInteger($invalidInteger)
    {
        $this->assertTrue($this->validator->validate($invalidInteger)->hasErrors());
    }

    /**
     * @test
     */
    public function integerValidatorCreatesTheCorrectErrorForAnInvalidSubject()
    {
        $this->assertEquals(1, count($this->validator->validate('not a number')->getErrors()));
    }
}
