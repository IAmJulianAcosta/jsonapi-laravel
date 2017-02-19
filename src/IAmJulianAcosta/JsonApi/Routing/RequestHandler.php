<?php
	/**
	 * Class RequestHandler
	 *
	 * @package IAmJulianAcosta\JsonApi\Routing
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Routing;
	
	use IAmJulianAcosta\JsonApi\Data\ErrorObject;
	use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\Request;
	use IAmJulianAcosta\JsonApi\Http\Response;
	use IAmJulianAcosta\JsonApi\Utils\ClassUtils;
	use Illuminate\Database\Eloquent\Collection;
	use function Stringy\create as s;
	
	class RequestHandler {
		
		/**
		 * @var Controller
		 */
		protected $controller;
		
		/**
		 * @var Request
		 */
		protected $request;
		
		public function __construct(Controller $controller) {
			$this->controller = $controller;
			$this->request = $controller->getRequest();
		}
		
		/**
		 * @return Model|Collection
		 */
		public function handleRequest () {
			$this->checkIfMethodIsSupported ();
			$methodName = ClassUtils::methodHandlerName($this->request->getMethod());
			$models = $this->{$methodName}();
			
			return $models;
		}
		
		/**
		 * Handle GET requests
		 * @return Model|Collection|null
		 */
		protected function handleGet () {
			$id = $this->request->getId();
			
			if (empty($id) === true) {
				$handler = new GetAllHandler($this->controller);
				return $handler->handle();
			}
			else {
				$handler = new GetSingleHandler($this->controller);
				return $handler->handle($this->request->getId());
			}
		}
		
		/**
		 * Handle POST requests
		 * @return Model
		 */
		protected function handlePost () {
			$handler = new PostHandler($this->controller);
			return $handler->handle($this->request->getId());
		}
		
		/**
		 * Handle PATCH requests
		 * @return Model|null
		 */
		protected function handlePatch () {
			$handler = new PatchHandler($this->controller);
			return $handler->handle($this->request->getId());
		}
		
		/**
		 * Handle PUT requests
		 * @return Model|null
		 */
		protected function handlePut () {
			return $this->handlePatch ();
		}
		
		/**
		 * Handle DELETE requests
		 * @return Model|null
		 */
		protected function handleDelete () {
			$handler = new DeleteHandler($this->controller);
			return $handler->handle($this->request->getId());
		}
		
		/**
		 * Check whether a method is supported for a model. If not supported, throws and exception
		 *
		 * @throws Exception
		 */
		protected function checkIfMethodIsSupported() {
			$method = $this->request->getMethod ();
			$controllerClass = get_class($this->controller);
			if (in_array(s($method)->toLowerCase (), $controllerClass::$supportedMethods) === false) {
				Exception::throwSingleException('Method not allowed', ErrorObject::HTTP_METHOD_NOT_ALLOWED,
					Response::HTTP_METHOD_NOT_ALLOWED);
			}
		}
		
	}