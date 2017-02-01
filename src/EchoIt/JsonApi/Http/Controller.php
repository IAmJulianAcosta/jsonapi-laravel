<?php

	namespace EchoIt\JsonApi\Http;
	
	use EchoIt\JsonApi\Data\LinkObject;
	use EchoIt\JsonApi\Data\LinksObject;
	use EchoIt\JsonApi\Data\RequestObject;
	use EchoIt\JsonApi\Data\ResourceObject;
	use EchoIt\JsonApi\Data\TopLevelObject;
	use EchoIt\JsonApi\Database\Eloquent\Model;
	use EchoIt\JsonApi\Data\ErrorObject;
	use EchoIt\JsonApi\Exception;
	use EchoIt\JsonApi\Cache\CacheManager;
	use EchoIt\JsonApi\Http\Response;
	use EchoIt\JsonApi\Http\Request;
	use EchoIt\JsonApi\QueryFilter;
	use EchoIt\JsonApi\SqlError;
	use EchoIt\JsonApi\Utils\ClassUtils;
	use EchoIt\JsonApi\Utils\ModelsUtils;
	use EchoIt\JsonApi\Utils\StringUtils;
	use EchoIt\JsonApi\Validation\ValidationException;
	use Illuminate\Database\Eloquent\Builder;
	use Illuminate\Database\Eloquent\ModelNotFoundException;
	use Illuminate\Database\QueryException;
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
		 * Controller constructor.
		 *
		 * @param Request $request
		 */
		public function __construct (Request $request) {
			$this->request = $request;
			$request->checkRequestContentType();
			$request->checkRequestAccept();
			$this->setResourceNameFromClassName();
			$request->extractData();
			if ($request->shouldHaveContent() === true) {
				$requestJsonApi = $this->requestJsonApi = $request->getJsonApiContent();
				$dataType       = StringUtils::dasherizedResourceName($this->resourceName);
				$requestJsonApi->setDataType($dataType);
				$requestJsonApi->validateRequiredParameters();
				$requestJsonApi->extractData();
			}
		}
		
		/**
		 * @param string $modelsNamespace
		 */
		public function setModelsNamespace($modelsNamespace) {
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
			$httpMethod = $this->request->getMethod ();

			if ($this->supportsMethod ($httpMethod) === false) {
				Exception::throwSingleException (
					'Method not allowed', ErrorObject::HTTP_METHOD_NOT_ALLOWED, Response::HTTP_METHOD_NOT_ALLOWED,
					static::ERROR_SCOPE
				);
			}
			
			//Executes the request
			$this->beforeHandleRequest();
			$model = $this->handleRequest ();
			$this->afterHandleRequest($model);

			if (is_null ($model)) {
				Exception::throwSingleException(
					'Unknown ID', ErrorObject::UNKNOWN_ERROR, Response::HTTP_NOT_FOUND, static::ERROR_SCOPE
				);
			}

			$this->beforeGenerateResponse($model);
			if ($httpMethod === 'GET') {
				$response = $this->generateCacheableResponse ($model);
			}
			else {
				$response = $this->generateNonCacheableResponse ($model);
			}
			$this->afterGenerateResponse($model, $response);
			return $response;
		}
		
		/**
		 * Check whether a method is supported for a model.
		 *
		 * @param  string $method HTTP method
		 * @return boolean
		 */
		public function supportsMethod($method) {
			return in_array(s($method)->toLowerCase (), $this->supportedMethods);
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
			
			if (empty($id)) {
				$models = $this->handleGetAll ();
				return $models;
			}
			else {
				return $this->handleGetSingle($id);
			}
		}
		
		/**
		 * @param         $id
		 *
		 * @return mixed
		 */
		protected function handleGetSingle ($id) {
			$this->beforeHandleGet();
			$this->requestType = static::GET;
			
			forward_static_call_array ([$this->fullModelName, 'validateUserGetSinglePermissions'], [$this->request, \Auth::user(), $id]);
			
			$key = CacheManager::getQueryCacheForSingleResource($id, StringUtils::dasherizedResourceName($this->resourceName));
			
			$model = Cache::remember(
				$key,
				static::$cacheTime,
				function () {
					$query = $this->generateSelectQuery ();
					
					$query->where('id', $this->request->getId());
					/** @var Model $model */
					$model = $query->first ();
					
					$this->afterModelQueried ($model);

					return $model;
				}
			);
			$this->afterHandleGet ($model);

			return $model;
		}

		/**
		 * @return Collection
		 */
		protected function handleGetAll () {
			$this->beforeHandleGetAll ();
			$this->requestType = static::GET_ALL;
			
			forward_static_call_array ([$this->fullModelName, 'validateUserGetAllPermissions'], [$this->request, \Auth::user()]);
			
			$key = CacheManager::getQueryCacheForMultipleResources(StringUtils::dasherizedResourceName($this->resourceName));
			$models = Cache::remember (
				$key, static::$cacheTime,
				function () {
					$query = $this->generateSelectQuery ();
					
					QueryFilter::filterRequest($this->request, $query);
					QueryFilter::sortRequest($this->request, $query, $this->fullModelName);
					$this->applyFilters ($query);
					
					try {
						//This method will execute get function inside paginate () or if not pagination present, inside itself.
						$model = QueryFilter::paginateRequest($this->request, $query);
					}
					catch (QueryException $exception) {
						throw new Exception(
							new Collection(
								new SqlError ('Database error', ErrorObject::DATABASE_ERROR,
									Response::HTTP_INTERNAL_SERVER_ERROR, $exception, static::ERROR_SCOPE
								)
							)
						);
						
					}
					
					$this->afterModelsQueried ($model);
					
					return $model;
				}
			);
			$this->afterHandleGetAll ($models);
			return $models;
		}

		/**
		 * Handle POST requests
		 *
		 * @return Model
		 * @throws Exception
		 * @throws ValidationException
		 */
		protected function handlePost () {
			$this->beforeHandlePost ();
			$this->requestType = static::POST;
			
			$modelName = $this->fullModelName;
			
			if (empty($this->requestJsonApi->getId ()) === false) {
				Exception::throwSingleException(
					"Creating a resource with a client generated ID is unsupported", ErrorObject::MALFORMED_REQUEST,
					Response::HTTP_FORBIDDEN, static::ERROR_SCOPE
				);
			}
			
			StringUtils::normalizeAttributes($this->requestJsonApi->getAttributes ());
			$attributes = $this->requestJsonApi->getAttributes ();
			
			/** @var Model $model */
			$model = forward_static_call_array ([$modelName, 'createAndValidate'], [$attributes]);
			if (empty($model) === true) {
				Exception::throwSingleException(
					'An unknown error occurred', ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR,
					static::ERROR_SCOPE
				);
			}
			
			$model->validateUserCreatePermissions ($this->request, Auth::user ());
			
			//Update relationships twice, first to update belongsTo and then to update polymorphic and others
			$model->updateRelationships ($this->requestJsonApi->getRelationships(), $this->modelsNamespace, true);
			
			$this->beforeSaveNewModel ($model);
			$this->saveModel($model);
			$this->afterSaveNewModel ($model);
			
			$model->updateRelationships ($this->requestJsonApi->getRelationships(), $this->modelsNamespace, true);
			$model->markChanged ();
			CacheManager::clearCache(StringUtils::dasherizedResourceName($this->resourceName));
			$this->afterHandlePost ($model);

			return $model;
		}
		
		/**
		 * Handle PATCH requests
		 *
		 * @return Model|null
		 * @throws Exception
		 */
		protected function handlePatch () {
			$this->beforeHandlePatch ();
			$this->requestType = static::PATCH;
			
			$modelName = $this->fullModelName;
			
			$model = $this->tryToFindModel($modelName);
			
			$model->validateUserUpdatePermissions ($this->request, Auth::user ());
			
			$originalAttributes = $model->getOriginal ();
			
			$attributes = $this->requestJsonApi->getAttributes();
			if (empty ($attributes) === false) {
				StringUtils::normalizeAttributes($attributes);
				
				forward_static_call_array ([$modelName, 'validateAttributes'], [$attributes]);
				$model->fill ($attributes);
			}

			$model->updateRelationships ($this->requestJsonApi->getRelationships(), $this->modelsNamespace, false);

			$this->beforeSaveModel ($model);
			$this->saveModel($model);
			$this->afterSaveModel ($model);
			
			$model->verifyIfModelChanged ($originalAttributes);

			if ($model->isChanged()) {
				CacheManager::clearCache (
					StringUtils::dasherizedResourceName($this->resourceName), $this->requestJsonApi->getId(), $model)
				;
			}
			
			$this->afterHandlePatch ($model);
			
			return $model;
		}
		
		/**
		 * Handle PATCH requests
		 * @return Model|null
		 */
		protected function handlePut () {
			return $this->handlePatch ();
		}

		/**
		 * Handle DELETE requests
		 *
		 * @return \EchoIt\JsonApi\Database\Eloquent\Model
		 * @throws \EchoIt\JsonApi\Exception
		 */
		protected function handleDelete () {
			$this->beforeHandleDelete ();
			$this->requestType = static::DELETE;
			
			if (empty($this->request->getId())) {
				Exception::throwSingleException(
					'No ID provided', ErrorObject::NO_ID, Response::HTTP_BAD_REQUEST, static::ERROR_SCOPE
				);
			}
			
			$modelName = $this->fullModelName;
			
			$model = $this->tryToFindModel($modelName);
			
			$model->validateUserDeletePermissions ($this->request, Auth::user ());
			
			if (is_null ($model)) {
				return null;
			}
			
			$model->delete ();
			
			$this->afterHandleDelete ($model);
			return $model;
		}
		
		/**
		 * @param $modelName
		 *
		 * @return Model
		 * @throws Exception
		 */
		protected function tryToFindModel($modelName) {
			try {
				/** @var Model $model */
				$id    = $this->request->getId();
				$model = $modelName::findOrFail($id);
				
				return $model;
			} catch (ModelNotFoundException $e) {
				$title = 'Record not found in Database';
				$code  = ErrorObject::UNKNOWN_ERROR;
				$status = Response::HTTP_NOT_FOUND;
				$resourceIdentifier = static::ERROR_SCOPE;
				Exception::throwSingleException($title, $code, $status, $resourceIdentifier);
			}
		}
		
		/**
		 * @param $model
		 *
		 * @throws Exception
		 */
		protected function saveModel(Model $model) {
			try {
				$model->saveOrFail();
			} catch (QueryException $exception) {
				throw new Exception(
					new Collection(
						new SqlError ('Database error', ErrorObject::DATABASE_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR,
							$exception, static::ERROR_SCOPE)
					)
				);
			} catch (\Exception $exception) {
				Exception::throwSingleException(
					'An unknown error occurred saving the record', ErrorObject::UNKNOWN_ERROR,
					Response::HTTP_INTERNAL_SERVER_ERROR, static::ERROR_SCOPE
				);
			}
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
			if (empty($id)) {
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
			$links = null;
			$modelsCollection = new Collection();
			$links = new LinksObject(
				new Collection(
					[
						new LinkObject("self", $this->request->fullUrl())
					]
				)
			);
			if ($models instanceof LengthAwarePaginator) {
				$paginator = $models;
				$modelsCollection = new Collection($paginator->items ());
				$links = $links->addLinks($this->getPaginationLinks($paginator));
			}
			else if ($models instanceof Collection) {
				$modelsCollection = $models;
			}
			else if ($models instanceof Model) {
				$modelsCollection = new Collection([$models]);
			}
			else {
				Exception::throwSingleException("Unknown error generating response", 0,
					Response::HTTP_INTERNAL_SERVER_ERROR, static::ERROR_SCOPE);
			}
			
			if ($loadRelations) {
				$this->loadRelatedModels($modelsCollection);
			}
			
			$included = ModelsUtils::getIncludedModels ($modelsCollection, $this->request);
			
			//If we have only a model, this will be the top level object, if not, will be a collection of ResourceObject
			if ($models instanceof Model) {
				$resourceObject = new ResourceObject($models);
			}
			else {
				$resourceObject = $modelsCollection->map(
					function (Model $model) {
						return new ResourceObject($model);
					}
				);
			}
			
			$topLevelObject = new TopLevelObject($resourceObject, null, null, $included, $links);
			
			$response = new Response($topLevelObject,
				static::successfulHttpStatusCode ($this->request->getMethod(), $topLevelObject, $models));
			
			return $response;
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
					if ($model instanceof Model && $model->isChanged()) {
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
		 * Generates a find query from model name
		 *
		 * @return Builder|null
		 */
		protected function generateSelectQuery() {
			$modelName = $this->fullModelName;
			return forward_static_call_array ([$modelName, 'generateSelectQuery'], [$this->request->getInclude()]);
		}
		
		/**
		 * This abstract function allows implementations to implement own filters
		 *
		 * @param $query
		 *
		 * @return void
		 */
		protected abstract function applyFilters (&$query);
		
		/**
		 * Method that runs before fullfilling a request. Should be implemented by child classes.
		 */
		protected function beforeFulfillRequest () {
			
		}
		
		/**
		 * Method that runs before handling a request. Should be implemented by child classes.
		 */
		protected function beforeHandleRequest () {
			
		}
		
		/**
		 * Method that runs after handling a request. Should be implemented by child classes.
		 */
		protected function afterHandleRequest ($models) {
			
		}
		
		/**
		 * Method that runs before generating the response. Should be implemented by child classes.
		 */
		protected function beforeGenerateResponse ($models) {
			
		}
		
		/**
		 * Method that runs after generating the response. Shouldn't be overridden by child classes.
		 */
		protected function afterGenerateResponse ($model, Response $response) {
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
		protected function beforeHandleGet () {
			
		}
		
		/**
		 * Method that runs after handling a GET request. Should be implemented by child classes.
		 *
		 * @param Model|null
		 */
		protected function afterHandleGet ($model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response. Should be implemented by child classes.
		 */
		protected function afterGenerateGetResponse ($model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a GET request of all resources. Should be implemented by child classes.
		 */
		protected function beforeHandleGetAll () {
			
		}
		
		/**
		 * Method that runs after handling a GET request of all resources. Should be implemented by child classes.
		 *
		 * @param Collection|LengthAwarePaginator $models
		 */
		protected function afterHandleGetAll ($models) {
			
		}
		
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		protected function afterGenerateGetAllResponse ($model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a POST request. Should be implemented by child classes.
		 */
		protected function beforeHandlePost () {
			
		}
		
		/**
		 * Method that runs after handling a POST request. Should be implemented by child classes.
		 */
		protected function afterHandlePost (Model $model) {
			
		}
		
		/**
		 * Method that runs after generating a POST response. Should be implemented by child classes.
		 */
		protected function afterGeneratePostResponse ($model, Response $response) {
			if ($model instanceof Model) {
				$response->header('Location', $model->getModelURL());
			}
		}
		
		/**
		 * Method that runs before handling a PATCH request. Should be implemented by child classes.
		 */
		protected function beforeHandlePatch () {
			
		}
		
		/**
		 * Method that runs after handling a PATCH request. Should be implemented by child classes.
		 */
		protected function afterHandlePatch ($model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		protected function afterGeneratePatchResponse ($model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a DELETE request. Should be implemented by child classes.
		 */
		protected function beforeHandleDelete () {
			
		}
		
		/**
		 * Method that runs after handling a DELETE request. Should be implemented by child classes.
		 */
		protected function afterHandleDelete ($model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		protected function afterGenerateDeleteResponse ($model, Response $response) {
			
		}
		
		/**
		 * Method that runs before saving a new model. Should be implemented by child classes.
		 *
		 * @param Model   $model
		 */
		protected function beforeSaveNewModel (Model $model) {
			
		}
		
		/**
		 * Method that runs after saving a new model. Should be implemented by child classes.
		 *
		 * @param Model   $model
		 */
		protected function afterSaveNewModel (Model $model) {
			
		}
		
		/**
		 * Method that runs before updating a model. Should be implemented by child classes.
		 *
		 * @param Model   $model
		 */
		protected function beforeSaveModel (Model $model) {
			
		}
		
		/**
		 * Method that runs after updating a model. Should be implemented by child classes.
		 *
		 * @param Model   $model
		 */
		protected function afterSaveModel (Model $model) {
			
		}
		
		protected function afterModelQueried ($model) {
			
		}
		
		protected function afterModelsQueried ( $model) {
			
		}
		
		/**
		 * @return \EchoIt\JsonApi\Http\Request
		 */
		public function getRequest() {
			return $this->request;
		}
		
		/**
		 * @param \EchoIt\JsonApi\Http\Request $request
		 */
		public function setRequest($request) {
			$this->request = $request;
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
