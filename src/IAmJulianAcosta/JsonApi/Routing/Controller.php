<?php

	namespace IAmJulianAcosta\JsonApi\Routing;
	
	use IAmJulianAcosta\JsonApi\Data\LinkObject;
	use IAmJulianAcosta\JsonApi\Data\LinksObject;
	use IAmJulianAcosta\JsonApi\Data\RequestObject;
	use IAmJulianAcosta\JsonApi\Data\ResourceObject;
	use IAmJulianAcosta\JsonApi\Data\TopLevelObject;
	use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
	use IAmJulianAcosta\JsonApi\Data\ErrorObject;
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Cache\CacheManager;
	use IAmJulianAcosta\JsonApi\Http\Response;
	use IAmJulianAcosta\JsonApi\Http\Request;
	use IAmJulianAcosta\JsonApi\QueryFilter;
	use IAmJulianAcosta\JsonApi\SqlError;
	use IAmJulianAcosta\JsonApi\Utils\ClassUtils;
	use IAmJulianAcosta\JsonApi\Utils\ModelsUtils;
	use IAmJulianAcosta\JsonApi\Utils\StringUtils;
	use IAmJulianAcosta\JsonApi\Validation\ValidationException;
	use Illuminate\Database\Eloquent\Builder;
	use Illuminate\Database\Eloquent\ModelNotFoundException;
	use Illuminate\Database\QueryException;
	use Illuminate\Pagination\Paginator;
	use Illuminate\Routing\Controller as BaseController;
	use Illuminate\Support\Collection;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\Cache;
	use Illuminate\Pagination\LengthAwarePaginator;
	use function Stringy\create as s;
	
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
		 * @var string Resource class name including namespace
		 */
		protected $fullModelName;
		
		/**
		 * @var string esource class name without namespace
		 */
		protected $shortModelName;

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
		protected $supportedMethods = ["get", "post", "put", "patch", "delete"];
		
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
			$this->setResourceNameFromClassName();
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
		
		//TODO breaking changes
		public function initializeModelNamespaces ($modelsNamespace) {
			$this->setModelsNamespace($modelsNamespace);
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
		
		/**
		 * Fulfills the API request and return a response. This is the entrypoint of controller.
		 *
		 * @return Response
		 * @throws Exception
		 */
		public function fulfillRequest () {
			$this->beforeFulfillRequest();
			
			$this->checkIfMethodIsSupported();
			
			//Executes the request
			$this->beforeHandleRequest();
			$model = $this->handleRequest ();
			$this->afterHandleRequest($model);
			
			$this->checkIfModelIsInvalid($model);

			$this->beforeGenerateResponse($model);
			$response = $this->generateAdequateResponse($model);
			$this->afterGenerateResponse($model, $response);
			return $response;
		}
		
		/**
		 * Check whether a method is supported for a model. If not supported, throws and exception
		 *
		 * @throws Exception
		 */
		private function checkIfMethodIsSupported() {
			$method = $this->request->getMethod ();
			if (in_array(s($method)->toLowerCase (), $this->supportedMethods) === false) {
				Exception::throwSingleException('Method not allowed', ErrorObject::HTTP_METHOD_NOT_ALLOWED,
					Response::HTTP_METHOD_NOT_ALLOWED, static::ERROR_SCOPE);
			}
		}
		
		private function checkIfModelIsInvalid ($model) {
			if (is_null ($model) === true) {
				Exception::throwSingleException(
					'Unknown ID', ErrorObject::UNKNOWN_ERROR, Response::HTTP_NOT_FOUND, static::ERROR_SCOPE
				);
			}
		}
		
		/**
		 * @return Model|Collection
		 */
		protected function handleRequest () {
			$methodName = ClassUtils::methodHandlerName($this->request->getMethod());
			$models = $this->{$methodName}();

			return $models;
		}
		
		/**
		 * @return Model|Collection|null
		 */
		protected function handleGet () {
			$id = $this->request->getId();
			
			if (empty($id) === true) {
				$handler = new GetAllHandler($this);
				return $handler->handle();
			}
			else {
				$handler = new GetSingleHandler($this);
				return $handler->handle($this->request->getId());
			}
		}
		
		protected function handlePost () {
			$handler = new PostHandler($this);
			return $handler->handle($this->request->getId());
		}
		
		protected function handlePatch () {
			$handler = new PatchHandler($this);
			return $handler->handle($this->request->getId());
		}
		
		/**
		 * Handle PATCH requests
		 * @return Model|null
		 */
		protected function handlePut () {
			return $this->handlePatch ();
		}
		
		protected function handleDelete () {
			$handler = new DeleteHandler($this);
			return $handler->handle($this->request->getId());
		}
		

		protected function generateAdequateResponse ($model) {
			if ($this->request->getMethod () === 'GET') {
				$response = $this->generateCacheableResponse ($model);
			}
			else {
				$response = $this->generateNonCacheableResponse ($model);
			}
			return $response;
		}
		
		/**
		 * Fullfills GET requests
		 *
		 * @param $models
		 *
		 * @return Response
		 */
		private function generateCacheableResponse ($models) {
			$id = $this->request->getId();
			if (empty($id) === true) {
				$key = CacheManager::getResponseCacheForMultipleResources(StringUtils::dasherizedResourceName($this->resourceName));
			}
			else {
				$key = CacheManager::getResponseCacheForSingleResource($id,
					StringUtils::dasherizedResourceName($this->resourceName));
			}
			
			return Cache::remember (
				$key, static::$cacheTime,
				function () use ($models) {
					return $this->generateResponse($models);
				}
			);
		}
		
		/**
		 * Fullfills POST, PATCH and DELETE requests
		 *
		 * @param $models
		 *
		 * @return Response
		 *
		 */
		private function generateNonCacheableResponse ($models) {
			return $this->generateResponse($models, false);
		}
		
		/**
		 * @param $models
		 * @param $loadRelations
		 * @return Response
		 */
		private function generateResponse ($models, $loadRelations = true) {
			$links = $this->generateResponseLinks();
			
			$modelsCollection = $this->getModelsAsCollection($models, $links);
			
			if ($loadRelations === true) {
				$this->loadRelatedModels($modelsCollection);
			}
			
			$included = ModelsUtils::getIncludedModels ($modelsCollection, $this->request);
			$resourceObject = $this->generateResourceObject($models, $modelsCollection);
			$topLevelObject = new TopLevelObject($resourceObject, null, null, $included, $links);
			
			$response = new Response($topLevelObject,
				static::successfulHttpStatusCode ($this->request->getMethod(), $topLevelObject, $models));
			
			return $response;
		}
		
		protected function generateResponseLinks () {
			return new LinksObject(
				new Collection(
					[
						new LinkObject("self", $this->request->fullUrl())
					]
				)
			);
		}
		
		protected function getModelsAsCollection ($models, LinksObject &$links) {
			if ($models instanceof LengthAwarePaginator === true) {
				/** @var LengthAwarePaginator $paginator */
				$paginator = $models;
				$modelsCollection = new Collection($paginator->items ());
				$links = $links->addLinks($this->getPaginationLinks($paginator));
			}
			else if ($models instanceof Collection === true) {
				$modelsCollection = $models;
			}
			else if ($models instanceof Model === true) {
				$modelsCollection = new Collection([$models]);
			}
			else {
				Exception::throwSingleException("Unknown error generating response", 0,
					Response::HTTP_INTERNAL_SERVER_ERROR, static::ERROR_SCOPE);
			}
			return $modelsCollection;
		}
		
		/**
		 * @param $models
		 *
		 * @return ResourceObject
		 */
		protected function generateResourceObject ($models, $modelsCollection) {
			//If we have only a model, this will be the top level object, if not, will be a collection of ResourceObject
			if ($models instanceof Model === true) {
				return new ResourceObject($modelsCollection->get(0));
			}
			else {
				return $modelsCollection->map(
					function (Model $model) {
						return new ResourceObject($model);
					}
				);
			}
		}
		
		/**
		 * Load related models before generating response
		 *
		 * @param $models
		 */
		private function loadRelatedModels(Collection $models) {
			/** @var Model $model */
			foreach ($models as $model) {
				$model->loadRelatedModels($this->request->getInclude());
			}
		}

		/**
		 * Return pagination links as array
		 * @param LengthAwarePaginator $paginator
		 * @return Collection
		 */
		protected function getPaginationLinks(LengthAwarePaginator $paginator) {
			$links = new Collection();
			
			$selfLink = urldecode($paginator->url($paginator->currentPage()));
			$firstLink = urldecode($paginator->url(1));
			$lastLink = urldecode($paginator->url($paginator->lastPage()));
			$previousLink = urldecode($paginator->url($paginator->currentPage() - 1));
			$nextLink = urldecode($paginator->nextPageUrl());
			
			$links->push(new LinkObject("first", $firstLink));
			$links->push(new LinkObject("last", $lastLink));
			
			if ($previousLink !== $selfLink && $previousLink !== '') {
				$links->push(new LinkObject("previous", $previousLink));
			}
			if ($nextLink !== $selfLink || $nextLink !== '') {
				$links->push(new LinkObject("next", $nextLink));
			}
			return $links;
		}
		
		/**
		 * A method for getting the proper HTTP status code for a successful request
		 *
		 * @param  string $method "PUT", "POST", "DELETE" or "GET"
		 * @param  Model|Collection|LengthAwarePaginator|null $model The model that a PUT request was executed against
		 * @return int
		 */
		public static function successfulHttpStatusCode($method, TopLevelObject $topLevelObject, $model = null) {
			// if we did a put request, we need to ensure that the model wasn't
			// changed in other ways than those specified by the request
			//     Ref: http://jsonapi.org/format/#crud-updating-responses-200

			switch ($method) {
				case 'POST':
					return Response::HTTP_CREATED;
				case 'PATCH':
				case 'PUT':
					if ($model instanceof Model === true && $model->isChanged() === true) {
						return Response::HTTP_OK;
					}
				case 'DELETE':
					if (empty($topLevelObject->getMeta()) === true) {
						return Response::HTTP_NO_CONTENT;
					}
					else {
						return Response::HTTP_OK;
					}
				case 'GET':
					return Response::HTTP_OK;
			}

			// Code shouldn't reach this point, but if it does we assume that the
			// client has made a bad request.
			return Response::HTTP_BAD_REQUEST;
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
				case static::GET;
					$this->afterGenerateGetResponse ($model, $response);
					break;
				case static::GET_ALL;
					$this->afterGenerateGetAllResponse($model, $response);
					break;
				case static::POST;
					$this->afterGeneratePostResponse($model, $response);
					break;
				case static::PATCH;
					$this->afterGeneratePatchResponse($model, $response);
					break;
				case static::DELETE;
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
		public function getFullModelName() {
			return $this->fullModelName;
		}
		
		/**
		 * @param string $fullModelName
		 */
		public function setFullModelName($fullModelName) {
			$this->fullModelName = $fullModelName;
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
		
		/**
		 * Generates resource name from class name
		 */
		private function setResourceNameFromClassName () {
			$shortClassName = ClassUtils::getControllerShortClassName(get_called_class());
			$resourceNameLength = strlen($shortClassName) - self::CONTROLLER_WORD_LENGTH;
			$this->resourceName = substr ($shortClassName, 0, $resourceNameLength);
		}
		
	}
