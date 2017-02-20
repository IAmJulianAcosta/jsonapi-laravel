<?php

	namespace IAmJulianAcosta\JsonApi\Routing;
	
	use IAmJulianAcosta\JsonApi\Data\RequestObject;
	use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\RequestInitializer;
	use IAmJulianAcosta\JsonApi\Http\Response;
	use IAmJulianAcosta\JsonApi\Http\Request;
	use IAmJulianAcosta\JsonApi\Utils\ClassUtils;
	use IAmJulianAcosta\JsonApi\Utils\StringUtils;
	use Illuminate\Routing\Controller as BaseController;
	use Illuminate\Support\Collection;
	use Illuminate\Pagination\LengthAwarePaginator;
	
	abstract class Controller extends BaseController {
		/**
		 * Override this const in the extended to distinguish model controllers from each other.
		 *
		 * See under default error codes which bits are reserved.
		 */
		const ERROR_SCOPE = 0;
		
		/**
		 * Used in reflection to generate resource name
		 */
		const CONTROLLER_WORD_LENGTH = 10;
		
		/**
		 * @var integer request type. Is set before fulfilling each kind of request.
		 */
		protected $requestType;
		
		const GET     = 0;
		const GET_ALL = 1;
		const POST    = 2;
		const PATCH   = 3;
		const DELETE  = 4;
		
		/**
		 * @var integer Amount time that response should be cached
		 */
		static protected $cacheTime = 60;
		
		
		/**
		 * @var string Controller resource name
		 */
		protected $resourceName;
		
		/**
		 * @var string Models namespace, required for reflection.
		 */
		protected $modelsNamespace;
		
		/**
		 * Supported methods for controller. Override to limit available methods.
		 *
		 * @var array
		 */
		protected static $supportedMethods = ["get", "post", "put", "patch", "delete"];
		
		/**
		 * @var Request HTTP Request from user.
		 */
		protected $request;
		
		/**
		 * @var RequestObject
		 */
		protected $requestJsonApi;
		
		/**
		 * If this is an auth controller
		 *
		 * @var bool
		 */
		protected static $isAuthController = false;
		
		/**
		 * Controller constructor.
		 *
		 * @param Request $request
		 */
		public function __construct (Request $request) {
			$this->request = $request;
			RequestInitializer::initialize($request);
			$request->checkHeaders();
			$this->setResourceNameFromClassName();
			$this->initializeRequest($request);
		}
		
		/**
		 * @param Request $request
		 */
		protected function initializeRequest(Request $request) {
			$request->extractData();
			$request->setAuthRequest(static::$isAuthController);
			if ($request->shouldHaveContent() === true) {
				$requestJsonApi = $this->requestJsonApi = $request->getJsonApiContent();
				$dataType       = StringUtils::dasherizedResourceName($this->resourceName);
				$requestJsonApi->setDataType($dataType);
				$requestJsonApi->validateRequiredParameters();
				$requestJsonApi->extractData();
			}
		}
		
		/**
		 * Generates resource name from class name
		 */
		protected function setResourceNameFromClassName () {
			$shortClassName = ClassUtils::getControllerShortClassName(get_called_class());
			$resourceNameLength = strlen($shortClassName) - self::CONTROLLER_WORD_LENGTH;
			$this->resourceName = substr ($shortClassName, 0, $resourceNameLength);
		}
		
		/**
		 * Fulfills the API request and return a response. This is the entrypoint of controller.
		 *
		 * @return Response
		 * @throws Exception
		 */
		public function fulfillRequest ($modelsNamespace) {
			$this->beforeFulfillRequest();
			
			//Executes the request
			$this->beforeHandleRequest();
			$requestHandler = new RequestHandler($this);
			$model          = $requestHandler->handleRequest ($modelsNamespace);
			$this->afterHandleRequest($model);
			
			$this->beforeGenerateResponse($model);
			$responseGenerator = new ResponseGenerator($this);
			$response = $responseGenerator->generateAdequateResponse($model);
			$this->afterGenerateResponse($model, $response);
			return $response;
		}
		
		/**
		 * This abstract function allows implementations to implement own filters
		 *
		 * @param $query
		 *
		 * @return void
		 */
		public abstract function applyFilters (&$query);
		
		/**
		 * Method that runs before fullfilling a request. Should be implemented by child classes.
		 */
		public function beforeFulfillRequest () {
			
		}
		
		/**
		 * Method that runs before handling a request. Should be implemented by child classes.
		 */
		public function beforeHandleRequest () {
			
		}
		
		/**
		 * Method that runs after handling a request. Should be implemented by child classes.
		 */
		public function afterHandleRequest ($models) {
			
		}
		
		/**
		 * Method that runs before generating the response. Should be implemented by child classes.
		 */
		public function beforeGenerateResponse ($models) {
			
		}
		
		/**
		 * Method that runs after generating the response. Shouldn't be overridden by child classes.
		 */
		public function afterGenerateResponse ($model, Response $response) {
			switch ($this->requestType) {
				case static::GET:
					$this->afterGenerateGetResponse ($model, $response);
					break;
				case static::GET_ALL:
					$this->afterGenerateGetAllResponse($model, $response);
					break;
				case static::POST:
					$this->afterGeneratePostResponse($model, $response);
					break;
				case static::PATCH:
					$this->afterGeneratePatchResponse($model, $response);
					break;
				case static::DELETE:
					$this->afterGenerateDeleteResponse($model, $response);
					break;
			}
		}
		
		/**
		 * Method that runs before handling a GET request. Should be implemented by child classes.
		 */
		public function beforeHandleGet () {
			
		}
		
		/**
		 * Method that runs after handling a GET request. Should be implemented by child classes.
		 *
		 * @param Model|null
		 */
		public function afterHandleGet ($model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response. Should be implemented by child classes.
		 */
		public function afterGenerateGetResponse ($model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a GET request of all resources. Should be implemented by child classes.
		 */
		public function beforeHandleGetAll () {
			
		}
		
		/**
		 * Method that runs after handling a GET request of all resources. Should be implemented by child classes.
		 *
		 * @param Collection|LengthAwarePaginator $models
		 */
		public function afterHandleGetAll ($models) {
			
		}
		
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		public function afterGenerateGetAllResponse ($model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a POST request. Should be implemented by child classes.
		 */
		public function beforeHandlePost () {
			
		}
		
		/**
		 * Method that runs after handling a POST request. Should be implemented by child classes.
		 */
		public function afterHandlePost (Model $model) {
			
		}
		
		/**
		 * Method that runs after generating a POST response. Should be implemented by child classes.
		 */
		public function afterGeneratePostResponse ($model, Response $response) {
			if ($model instanceof Model === true) {
				/** @var Model $model */
				$response->header('Location', $model->getModelURL());
			}
		}
		
		/**
		 * Method that runs before handling a PATCH request. Should be implemented by child classes.
		 */
		public function beforeHandlePatch () {
			
		}
		
		/**
		 * Method that runs after handling a PATCH request. Should be implemented by child classes.
		 */
		public function afterHandlePatch ($model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		public function afterGeneratePatchResponse ($model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a DELETE request. Should be implemented by child classes.
		 */
		public function beforeHandleDelete () {
			
		}
		
		/**
		 * Method that runs after handling a DELETE request. Should be implemented by child classes.
		 */
		public function afterHandleDelete ($model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		public function afterGenerateDeleteResponse ($model, Response $response) {
			
		}
		
		/**
		 * Method that runs before saving a new model. Should be implemented by child classes.
		 *
		 * @param Model   $model
		 */
		public function beforeSaveNewModel (Model $model) {
			
		}
		
		/**
		 * Method that runs after saving a new model. Should be implemented by child classes.
		 *
		 * @param Model   $model
		 */
		public function afterSaveNewModel (Model $model) {
			
		}
		
		/**
		 * Method that runs before updating a model. Should be implemented by child classes.
		 *
		 * @param Model   $model
		 */
		public function beforeSaveModel (Model $model) {
			
		}
		
		/**
		 * Method that runs after updating a model. Should be implemented by child classes.
		 *
		 * @param Model   $model
		 */
		public function afterSaveModel (Model $model) {
			
		}
		
		public function afterModelQueried ($model) {
			
		}
		
		public function afterModelsQueried ( $model) {
			
		}
		
		/**
		 * @return \IAmJulianAcosta\JsonApi\Http\Request
		 */
		public function getRequest() {
			return $this->request;
		}
		
		/**
		 * @param \IAmJulianAcosta\JsonApi\Http\Request $request
		 */
		public function setRequest($request) {
			$this->request = $request;
		}
		
		/**
		 * @return string
		 */
		public function getResourceName() {
			return $this->resourceName;
		}
		
		/**
		 * @param string $resourceName
		 */
		public function setResourceName($resourceName) {
			$this->resourceName = $resourceName;
		}
		
		/**
		 * @return int
		 */
		public function getRequestType() {
			return $this->requestType;
		}
		
		/**
		 * @param int $requestType
		 */
		public function setRequestType($requestType) {
			$this->requestType = $requestType;
		}
		
		/**
		 * @return RequestObject
		 */
		public function getRequestJsonApi() {
			return $this->requestJsonApi;
		}
		
		/**
		 * @param RequestObject $requestJsonApi
		 */
		public function setRequestJsonApi($requestJsonApi) {
			$this->requestJsonApi = $requestJsonApi;
		}
		
		/**
		 * @return string
		 */
		public function getModelsNamespace() {
			return $this->modelsNamespace;
		}
		
		/**
		 * @param string $modelsNamespace
		 */
		public function setModelsNamespace($modelsNamespace) {
			$this->modelsNamespace = $modelsNamespace;
		}
		
	}
