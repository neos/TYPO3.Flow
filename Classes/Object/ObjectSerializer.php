<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\Object;

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
 * The object serializer. This serializer traverses an object tree and transforms
 * it into an array.
 * Dependant objects are only included if they are not singleton and the property
 * is not annotated transient.
 * Afterwards it can reconstitute the objects from the array.
 *
 * @version $Id$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class ObjectSerializer {

	/**
	 * Objects stored as an array of properties
	 * @var array
	 */
	protected $objectsAsArray = array();

	/**
	 * The object manager
	 * @var F3\FLOW3\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * The reflection service
	 * @var F3\FLOW3\Reflection\ServiceInterface
	 */
	protected $reflectionService;

	/**
	 * The persistence manager
	 * @var F3\FLOW3\Persistence\PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * Injects the object manager
	 *
	 * @param F3\FLOW3\Object\ObjectManagerInterface $objectManager The object manager
	 * @return void
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function injectObjectManager(\F3\FLOW3\Object\ObjectManagerInterface $objectManager) {
		$this->objectManager = $objectManager;
	}

	/**
	 * Injects the reflection service
	 *
	 * @param F3\FLOW3\Reflection\ReflectionService $reflectionService The reflection service
	 * @return void
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function injectReflectionService(\F3\FLOW3\Reflection\ReflectionService $reflectionService) {
		$this->reflectionService = $reflectionService;
	}

	/**
	 * Inject the persistence manager
	 *
	 * @param F3\FLOW3\Persistence\PersistenceManagerInterface $persistenceManager The persistence manager
	 * @return void
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function injectPersistenceManager(\F3\FLOW3\Persistence\PersistenceManagerInterface $persistenceManager) {
		$this->persistenceManager = $persistenceManager;
	}

	/**
	 * Clears the internal state, discarding all stored objects.
	 *
	 * @return void
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function clearState() {
		unset($this->objectsAsArray);
	}

	/**
	 * Serializes an object as property array.
	 *
	 * @param object $object The object to store in the registry
	 * @return array The property array
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function serializeObjectAsPropertyArray($object, $isTopLevelItem = TRUE) {
		if (isset($this->objectsAsArray[spl_object_hash($object)])) {
			return $this->objectsAsArray;
		}

		$className = ($object instanceof \F3\FLOW3\AOP\ProxyInterface) ? $object->FLOW3_AOP_Proxy_getProxyTargetClassName() : get_class($object);

		$propertyArray = array();
		foreach ($this->reflectionService->getClassPropertyNames($className) as $propertyName) {

			if ($this->reflectionService->isPropertyTaggedWith($className, $propertyName, 'transient')) continue;

			if ($object instanceof \F3\FLOW3\AOP\ProxyInterface) {
				$propertyValue = $object->FLOW3_AOP_Proxy_getProperty($propertyName);
			} else {
				$propertyReflection = new \F3\FLOW3\Reflection\PropertyReflection($className, $propertyName);
				$propertyValue = $propertyReflection->getValue($object);
			}

			$propertyClassName = '';
			if (is_object($propertyValue)) {
				$propertyClassName = get_class($propertyValue);
			}

			if ($propertyClassName === 'SplObjectStorage') {
				$propertyArray[$propertyName]['type'] = 'SplObjectStorage';
				$propertyArray[$propertyName]['value'] = array();

				foreach ($propertyValue as $storedObject) {
					$objectHash = spl_object_hash($storedObject);
					$propertyArray[$propertyName]['value'][] = $objectHash;
					$this->serializeObjectAsPropertyArray($storedObject, FALSE);
				}
			} elseif (is_object($propertyValue) && $propertyValue instanceof \ArrayObject) {
				$propertyArray[$propertyName]['type'] = 'ArrayObject';
				$propertyArray[$propertyName]['value'] = $this->buildStorageArrayForArrayProperty($propertyValue->getArrayCopy());

			} elseif (is_object($propertyValue)
						&& $propertyValue instanceof \F3\FLOW3\AOP\ProxyInterface
						&& $propertyValue instanceof \F3\FLOW3\Persistence\Aspect\PersistenceMagicInterface
						&& $this->persistenceManager->isNewObject($propertyValue) === FALSE
						&& ($this->reflectionService->isClassTaggedWith($propertyClassName, 'entity')
						|| $this->reflectionService->isClassTaggedWith($propertyClassName, 'valueobject'))) {

				$propertyArray[$propertyName]['type'] = 'persistenceObject';
				$propertyArray[$propertyName]['value']['className'] = $propertyValue->FLOW3_AOP_Proxy_getProxyTargetClassName();
				$propertyArray[$propertyName]['value']['UUID'] = $this->persistenceManager->getIdentifierByObject($propertyValue);

			} elseif (is_object($propertyValue)) {
				$propertyObjectName = $this->objectManager->getObjectNameByClassName($propertyClassName);
				if ($this->objectManager->getScope($propertyObjectName) === \F3\FLOW3\Object\Configuration\Configuration::SCOPE_SINGLETON) continue;

				$objectHash = spl_object_hash($propertyValue);
				$propertyArray[$propertyName]['type'] = 'object';
				$propertyArray[$propertyName]['value'] = $objectHash;
				$this->serializeObjectAsPropertyArray($propertyValue, FALSE);

			} elseif (is_array($propertyValue)) {
				$propertyArray[$propertyName]['type'] = 'array';
				$propertyArray[$propertyName]['value'] = $this->buildStorageArrayForArrayProperty($propertyValue);

			} else {
				$propertyArray[$propertyName]['type'] = 'simple';
				$propertyArray[$propertyName]['value'] = $propertyValue;
			}
		}

		$this->objectsAsArray[spl_object_hash($object)] = array(
			'className' => $className,
			'topLevelItem' => $isTopLevelItem,
			'properties' => $propertyArray
		);

		return $this->objectsAsArray;
	}

	/**
	 * Builds a storable array out of an array property. It calls itself recursively
	 * for multidimensional arrays. For objects putObject() ist called with the object's
	 * hash value as $objectName.
	 *
	 * @param array $arrayProperty The source array property
	 * @return array The array property to store
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	protected function buildStorageArrayForArrayProperty(array $arrayProperty) {
		$storableArray = array();

		foreach ($arrayProperty as $key => $value) {
			$storableArray[$key] = array();

			if (is_array($value)) {
				$storableArray[$key]['type'] = 'array';
				$storableArray[$key]['value'] = $this->buildStorageArrayForArrayProperty($value);
			} else if (is_object($value)) {
				$storableArray[$key]['type'] = 'object';
				$storableArray[$key]['value'] = spl_object_hash($value);

				$this->serializeObjectAsPropertyArray($value, FALSE);
			} else {
				$storableArray[$key]['type'] = 'simple';
				$storableArray[$key]['value'] = $value;
			}
		}

		return $storableArray;
	}

	/**
	 * Deserializes a given object tree and reinjects all dependencies.
	 *
	 * @param array $dataArray The serialized objects array
	 * @return array The deserialized objects in an array
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function deserializeObjectsArray(array $dataArray) {
		$this->objectsAsArray = $dataArray;
		$objects = array();

		foreach ($this->objectsAsArray as $objectHash => $objectData) {
			if (!$objectData['topLevelItem'] || !$this->objectManager->isRegistered($objectData['className'])) continue;
			$objects[$objectHash] = $this->reconstituteObject($objectData);
		}

		return $objects;
	}

	/**
	 * Reconstitutes an object from a serialized object without calling the constructor.
	 *
	 * @param array $objectData The object data array
	 * @return void
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	protected function reconstituteObject(array $objectData) {
		$object = $this->objectManager->recreate($objectData['className']);

		foreach ($objectData['properties'] as $propertyName => $propertyData) {
			switch($propertyData['type']) {
				case 'simple':
					$propertyValue = $propertyData['value'];
					break;
				case 'array':
					$propertyValue = $this->reconstituteArray($propertyData['value']);
					break;
				case 'ArrayObject':
					$propertyValue = new \ArrayObject($this->reconstituteArray($propertyData['value']));
					break;
				case 'object':
					$propertyValue = $this->reconstituteObject($this->objectsAsArray[$propertyData['value']]);
					break;
				case 'SplObjectStorage':
					$propertyValue = $this->reconstituteSplObjectStorage($propertyData['value']);
					break;
				case 'persistenceObject':
					$propertyValue = $this->reconstitutePersistenceObject($propertyData['value']['className'], $propertyData['value']['UUID']);
					break;
			}

			if ($object instanceof \F3\FLOW3\AOP\ProxyInterface) {
				$object->FLOW3_AOP_Proxy_setProperty($propertyName, $propertyValue);
			} else {
				$reflectionProperty = new \ReflectionProperty(get_class($object), $propertyName);
				$reflectionProperty->setAccessible(TRUE);
				$reflectionProperty->setValue($object, $propertyValue);
			}
		}

		return $object;
	}

	/**
	 * Reconstitutes an array from a data array.
	 *
	 * @param array $dataArray The data array to reconstitute from
	 * @return array The reconstituted array
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	protected function reconstituteArray($dataArray) {
		$result = array();

		foreach ($dataArray as $key => $entryData) {
			$value = NULL;

			switch($entryData['type']) {
				case 'simple':
					$value = $entryData['value'];
					break;
				case 'array':
					$value = $this->reconstituteArray($entryData['value']);
					break;
				case 'object':
					$value = $this->reconstituteObject($this->objectsAsArray[$entryData['value']]);
					break;
				case 'SplObjectStorage':
					$value = $this->reconstituteSplObjectStorage($this->objectsAsArray[$entryData['value']]['className'], $this->objectsAsArray[$entryData['value']]);
					break;
				case 'persistenceObject':
					$value = $this->reconstitutePersistenceObject($entryData['value']['className'], $entryData['value']['UUID']);
					break;
			}

			$result[$key] = $value;
		}

		return $result;
	}

	/**
	 * Reconstitutes an SplObjectStorage from a data array.
	 *
	 * @param array $dataArray The data array to reconstitute from
	 * @return SplObjectStorage The reconstituted SplObjectStorage
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	protected function reconstituteSplObjectStorage($dataArray) {
		$result = new \SplObjectStorage();

		foreach ($dataArray as $objectName) {
			$result->attach($this->reconstituteObject($this->objectsAsArray[$objectName]));
		}

		return $result;
	}

	/**
	 * Reconstitutes a persistence object (entity or valueobject) identified by the given UUID.
	 *
	 * @param string $className The class name of the object to retrieve
	 * @param string $uuid The UUID of the object
	 * @return object The reconstituted persistence object, NULL if none was found
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	protected function reconstitutePersistenceObject($className, $uuid) {
		return $this->persistenceManager->getObjectByIdentifier($uuid);
	}
}

?>