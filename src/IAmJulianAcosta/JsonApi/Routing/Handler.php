<?php
	/**
	 * Class Handler
	 *
	 * @package IAmJulianAcosta\JsonApi\Routing
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Routing;
	
	use IAmJulianAcosta\JsonApi\Data\RequestObject;
	use IAmJulianAcosta\JsonApi\Http\Request;
	
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
		 * @var Request
		 */
		protected $request;
		
		/**
		 * @var RequestObject
		 */
		protected $requestJsonApi;
		
		public function __construct(Controller $controller) {
			$this->controller = $controller;
			$this->fullModelName = $controller->getFullModelName();
			$this->request = $controller->getRequest();
			$this->requestJsonApi = $controller->getRequestJsonApi();
			$this->resourceName = $controller->getResourceName();
		}
		
		public abstract function handle ($id = null);
	}
