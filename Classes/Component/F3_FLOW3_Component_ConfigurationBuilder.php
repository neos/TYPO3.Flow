<?php
declare(ENCODING = 'utf-8');
namespace F3::FLOW3::Component;

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
 * @subpackage Component
 * @version $Id$
 */

/**
 * Component Configuration Builder which can build component configuration objects
 * from a generic configuration container.
 *
 * @package FLOW3
 * @subpackage Component
 * @version $Id$
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class ConfigurationBuilder {

	/**
	 * Builds a component configuration object from a generic configuration container.
	 *
	 * @param string $componentName Name of the component
	 * @param array configurationArray The configuration array with options for the component configuration
	 * @param string configurationSourceHint A human readable hint on the original source of the configuration (for troubleshooting)
	 * @param F3::FLOW3::Component::Configuration existingComponentConfiguration If set, this component configuration object will be used instead of creating a fresh one
	 * @return F3::FLOW3::Component::Configuration The component configuration object
	 * @author Robert Lemke <robert@typo3.org>
	 */
	static public function buildFromConfigurationArray($componentName, array $configurationArray, $configurationSourceHint = '', $existingComponentConfiguration = NULL) {
		$className = (isset($configurationArray['className']) ? $configurationArray['className'] : $componentName);
		$componentConfiguration = ($existingComponentConfiguration instanceof F3::FLOW3::Component::Configuration) ? $componentConfiguration = $existingComponentConfiguration : new F3::FLOW3::Component::Configuration($componentName, $className);
		$componentConfiguration->setConfigurationSourceHint($configurationSourceHint);

		foreach ($configurationArray as $optionName => $optionValue) {
			switch ($optionName) {
				case 'scope':
					$componentConfiguration->setScope(self::parseScope($optionValue));
				break;
				case 'properties':
					if (is_array($optionValue)) {
						foreach ($optionValue as $propertyName => $propertyValue) {
							if (is_array($propertyValue) && isset($propertyValue['reference'])) {
								$propertyType = F3::FLOW3::Component::ConfigurationProperty::PROPERTY_TYPES_REFERENCE;
								$property = new F3::FLOW3::Component::ConfigurationProperty($propertyName, $propertyValue['reference'], $propertyType);
							} else {
								$propertyType = F3::FLOW3::Component::ConfigurationProperty::PROPERTY_TYPES_STRAIGHTVALUE;
								$property = new F3::FLOW3::Component::ConfigurationProperty($propertyName, $propertyValue, $propertyType);
							}
							$componentConfiguration->setProperty($property);
						}
					}
				break;
				case 'constructorArguments':
					if (is_array($optionValue)) {
						foreach ($optionValue as $argumentName => $argumentValue) {
							if (is_array($argumentValue) && isset($argumentValue->reference)) {
								$argumentType = F3::FLOW3::Component::ConfigurationArgument::ARGUMENT_TYPES_REFERENCE;
								$argument = new F3::FLOW3::Component::ConfigurationArgument($argumentName, $argumentValue['reference'], $argumentType);
							} else {
								$argumentType = F3::FLOW3::Component::ConfigurationArgument::ARGUMENT_TYPES_STRAIGHTVALUE;
								$argument = new F3::FLOW3::Component::ConfigurationArgument($argumentName, $argumentValue, $argumentType);
							}
							$componentConfiguration->setConstructorArgument($argument);
						}
					}
				break;
				case 'className':
				case 'lifecycleInitializationMethod':
					$methodName = 'set' . ucfirst($optionName);
					$componentConfiguration->$methodName(trim($optionValue));
				break;
				case 'AOPAddToProxyBlacklist':
				break;
				case 'autoWiringMode':
					$methodName = 'set' . ucfirst($optionName);
					$componentConfiguration->$methodName($optionValue == TRUE);
				break;
				default:
					throw new F3::FLOW3::Component::Exception::InvalidComponentConfiguration('Invalid configuration option "' . $optionName . '" (source: ' . $componentConfiguration->getConfigurationSourceHint() . ')', 1167574981);
			}
		}
		return $componentConfiguration;
	}

	/**
	 * Parses the value of the option "scope"
	 *
	 * @param  string $value: Value of the option
	 * @return integer The scope translated into a scope constant
	 * @throws F3::FLOW3::Component::Exception::InvalidComponentConfiguration if an invalid scope has been specified
	 * @author Robert Lemke <robert@typo3.org>
	 */
	static protected function parseScope($value) {
		if (!in_array($value, array('singleton', 'prototype', 'session'))) throw new F3::FLOW3::Component::Exception::InvalidComponentConfiguration('Invalid scope', 1167574991);
		return $value;
	}
}
?>