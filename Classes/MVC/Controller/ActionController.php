<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\MVC\Controller;

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
 * @subpackage MVC
 * @version $Id$
 */

/**
 * A multi action controller
 *
 * @package FLOW3
 * @subpackage MVC
 * @version $Id$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class ActionController extends \F3\FLOW3\MVC\Controller\AbstractController {

	/**
	 * @var \F3\FLOW3\Object\ManagerInterface
	 */
	protected $objectManager;

	/**
	 * @var \F3\FLOW3\Reflection\Service
	 */
	protected $reflectionService;

	/**
	 * @var boolean If initializeView() should be called on an action invocation.
	 */
	protected $initializeView = TRUE;

	/**
	 * By default a view with the same name as the current action is provided. Contains NULL if none was found.
	 * @var \F3\FLOW3\MVC\View\ViewInterface
	 */
	protected $view = NULL;

	/**
	 * By default a matching view will be resolved. If this property is set, automatic resolving is disabled and the specified object is used instead.
	 * @var string
	 */
	protected $viewObjectName = NULL;

	/**
	 * Name of the action method
	 * @var string
	 */
	protected $actionMethodName = 'indexAction';

	/**
	 * Injects the object manager
	 *
	 * @param \F3\FLOW3\Object\ManagerInterface $objectManager
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function injectObjectManager(\F3\FLOW3\Object\ManagerInterface $objectManager) {
		$this->objectManager = $objectManager;
	}

	/**
	 * Injects the reflection service
	 *
	 * @param \F3\FLOW3\Reflection\Service $reflectionService
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function injectReflectionService(\F3\FLOW3\Reflection\Service $reflectionService) {
		$this->reflectionService = $reflectionService;
	}

	/**
	 * Checks if the current request type is supported by the controller.
	 *
	 * @param \F3\FLOW3\MVC\Request $request The current request
	 * @return boolean TRUE if this request type is supported, otherwise FALSE
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function canProcessRequest(\F3\FLOW3\MVC\Request $request) {
		if (!$request instanceof \F3\FLOW3\MVC\Web\Request) return FALSE;
		return parent::canProcessRequest($request);
	}

	/**
	 * Handles a request. The result output is returned by altering the given response.
	 *
	 * @param \F3\FLOW3\MVC\Request $request The request object
	 * @param \F3\FLOW3\MVC\Response $response The response, modified by this handler
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function processRequest(\F3\FLOW3\MVC\Request $request, \F3\FLOW3\MVC\Response $response) {
		$this->request = $request;
		$this->request->setDispatched(TRUE);
		$this->response = $response;

		$this->actionMethodName = $this->resolveActionMethodName();
		$this->initializeActionMethodArguments();
		$this->initializeArguments();
		$this->mapRequestArgumentsToLocalArguments();
		if ($this->initializeView) $this->initializeView();
		$this->initializeAction();

		$this->callActionMethod();
	}

	/**
	 * Implementation of the arguments initilization in the action controller:
	 * Automatically registers arguments of the current action
	 *
	 * Don't override this method - use initializeArguments() instead.
	 *
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 * @see initializeArguments()
	 * @internal
	 */
	protected function initializeActionMethodArguments() {
		$methodParameters = $this->reflectionService->getMethodParameters(get_class($this), $this->actionMethodName);
		$methodTagsAndValues = $this->reflectionService->getMethodTagsValues(get_class($this), $this->actionMethodName);
		foreach ($methodParameters as $parameterName => $parameterInfo) {
			$dataType = 'Text';
			if (isset($methodTagsAndValues['param']) && count($methodTagsAndValues['param']) > 0) {
				$explodedTagValue = explode(' ', array_shift($methodTagsAndValues['param']));
				switch ($explodedTagValue[0]) {
					case 'integer' :
						$dataType = 'Integer';
					break;
					default:
						if (strpos($dataType, '\\') !== FALSE) {
							$dataType = $explodedTagValue[0];
						}
				}
			}
			$this->arguments->addNewArgument($parameterName, $dataType);
		}
	}

	/**
	 * Determines the action method and assures that the method exists.
	 *
	 * @return string The action method name
	 * @throws \F3\FLOW3\MVC\Exception\NoSuchAction if the action specified in the request object does not exist (and if there's no default action either).
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function resolveActionMethodName() {
		$actionMethodName = $this->request->getControllerActionName() . 'Action';
		if (!method_exists($this, $actionMethodName)) throw new \F3\FLOW3\MVC\Exception\NoSuchAction('An action "' . $actionMethodName . '" does not exist in controller "' . get_class($this) . '".', 1186669086);
		return $actionMethodName;
	}

	/**
	 * Calls the specified action method and passes the arguments.
	 * If the action returns a string, it is appended to the content in the
	 * response object.
	 *
	 * @param string $actionMethodName Name of the action method
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function callActionMethod() {
		$preparedArguments = array();
		foreach ($this->arguments as $argument) {
			$preparedArguments[] = $argument->getValue();
		}

		$actionResult = call_user_func_array(array($this, $this->actionMethodName), $preparedArguments);
		if ($actionResult === NULL && $this->view instanceof \F3\FLOW3\MVC\View\ViewInterface) {
			$this->response->appendContent($this->view->render());
		} elseif (is_string($actionResult) && strlen($actionResult) > 0) {
			$this->response->appendContent($actionResult);
		}
	}

	/**
	 * Prepares a view for the current action and stores it in $this->view.
	 * By default, this method tries to locate a view with a name matching
	 * the current action.
	 *
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function initializeView() {
		$viewObjectName = ($this->viewObjectName === NULL) ? $this->request->getViewObjectName() : $this->viewObjectName;
		if ($viewObjectName === FALSE) $viewObjectName = 'F3\FLOW3\MVC\View\EmptyView';

		$this->view = $this->objectManager->getObject($viewObjectName);
		$this->view->setRequest($this->request);
	}

	/**
	 * Initializes the controller before invoking an action method.
	 *
	 * Override this method to solve tasks which all actions have in
	 * common.
	 *
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function initializeAction() {
	}

	/**
	 * The default action of this controller.
	 *
	 * This method should always be overridden by the concrete action
	 * controller implementation.
	 *
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function indexAction() {
		return 'No index action has been implemented yet for this controller.';
	}
}
?>