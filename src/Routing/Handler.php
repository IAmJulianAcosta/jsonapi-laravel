<?php
	/**
	 * Class Handler
	 *
	 * @package IAmJulianAcosta\JsonApi\Routing
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Routing;
	
	use IAmJulianAcosta\JsonApi\Data\RequestObject;
	use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
	use IAmJulianAcosta\JsonApi\Http\Request;
	use IAmJulianAcosta\JsonApi\Utils\ClassUtils;
	
	abstract class Handler {
		
		/**
		 * @var Controller
		 */
		protected $controller;
		
		/**
		 * @var string
		 */
		protected $fullModelName;
		
		/**
		 * @var string
		 */
		protected $shortModelName;
		
		/**
		 * @var Request
		 */
		protected $request;
		
		/**
		 * @var RequestObject
		 */
		protected $requestJsonApi;
		
		/**
		 * @var string
		 */
		protected $resourceName;
		
		protected $modelsNamespace;
		
		public function __construct(Controller $controller, $modelsNamespace) {
			$this->controller = $controller;
			$this->modelsNamespace = $modelsNamespace;
			$this->generateModelName();
			$this->checkModelInheritance();
			$this->request = $controller->getRequest();
			$this->requestJsonApi = $controller->getRequestJsonApi();
			$this->resourceName = $controller->getResourceName();
		}
		
		abstract public function handle ($id = null);
		
		public function initializeModelNamespaces ($modelsNamespace) {
			$this->modelsNamespace = $modelsNamespace;
			$this->generateModelName ();
			$this->checkModelInheritance();
			forward_static_call([$this->fullModelName, 'checkRequiredClassProperties']);
		}
		
		/**
		 * Generates model names from controller name class
		 */
		protected function generateModelName () {
			$shortName = $this->resourceName;
			$this->shortModelName = ClassUtils::getModelClassName ($shortName, $this->modelsNamespace, true, true);
			$this->fullModelName = ClassUtils::getModelClassName ($shortName, $this->modelsNamespace, true);
		}
		
		/**
		 * Check if this model inherits from JsonAPI Model
		 */
		protected function checkModelInheritance () {
			if (is_subclass_of($this->fullModelName, Model::class) === false) {
				Model::throwInheritanceException($this->fullModelName);
			}
		}
	}
