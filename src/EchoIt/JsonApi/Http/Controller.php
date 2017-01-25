<?php

	namespace EchoIt\JsonApi\Http;
	
	use EchoIt\JsonApi\Database\Eloquent\Model;
	use EchoIt\JsonApi\Error;
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
	use Illuminate\Database\QueryException;
	use Illuminate\Routing\Controller as BaseController;
	use Illuminate\Support\Collection;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\Cache;
	use Illuminate\Support\Pluralizer;
	use Illuminate\Pagination\LengthAwarePaginator;
	use function Stringy\create as s;
	
	abstract class Controller extends BaseController {

		/**
		 * Override this const in the extended to distinguish model controllers from each other.
		 *
		 * See under default error codes which bits are reserved.
		 */
		const ERROR_SCOPE = 0;

		const CONTROLLER_WORD_LENGTH = 10;
		
		protected static $namespace;
		protected static $exposedRelations;
		
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
		 * @var Model Class name used by this controller including namespace
		 */
		protected $fullModelName;

		/**
		 * @var integer Amount time that response should be cached
		 */
		static protected $cacheTime = 60;

		/**
		 * @var Model Resource name in lowercase
		 */
		protected $shortModelName;

		/**
		 * @var string Controller resource name
		 */
		protected $resourceName;
		
		protected $modelsNamespace;
		
		/**
		 * Supported methods for controller. Override to limit available methods.
		 *
		 * @var array
		 */
		protected $supportedMethods = ["get", "post", "put", "patch", "delete"];
		
		/**
		 * @var Request
		 */
		protected $request;
		
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
		 * Controller constructor.
		 *
		 * @param Request $request
		 */
		public function __construct (Request $request) {
			$this->request = $request;
			$this->setResourceName ();
			if(is_null(static::$exposedRelations)) {
				throw new \InvalidArgumentException ('Controller does not have defined $exposedRelations property');
			}
		}
		
		/**
		 * @param string $modelsNamespace
		 */
		public function setModelsNamespace($modelsNamespace) {
			$this->modelsNamespace = $modelsNamespace;
			$this->generateModelName ();
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
				throw new Exception(
					[
						new Error ('Method not allowed', Error::HTTP_METHOD_NOT_ALLOWED,
							Response::HTTP_METHOD_NOT_ALLOWED, static::ERROR_SCOPE
						)
				    ]
				);
			}

			//Validates if this resource could be updated/deleted/created by all users.
			if ($httpMethod !== 'GET' && $this->allowsModifyingByAllUsers () === false) {
				throw new Exception(
					[
						new Error ('This user cannot modify this resource', Error::UNAUTHORIZED,
							Response::HTTP_FORBIDDEN, static::ERROR_SCOPE
						)
					]
				);
			}
			
			//Executes the request
			$this->beforeHandleRequest();
			$model = $this->handleRequest ();
			$this->afterHandleRequest($model);

			if (is_null ($model)) {
				throw new Exception(
					[
						new Error ('Unknown ID', Error::UNKNOWN_ERROR, Response::HTTP_NOT_FOUND, static::ERROR_SCOPE)
				    ]
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
			$included = null;
			if ($models instanceof LengthAwarePaginator) {
				$paginator = $models;
				$modelsCollection = new Collection($paginator->items ());
				$links = $this->getPaginationLinks ($paginator);
			}
			else if ($models instanceof Collection) {
				$modelsCollection = $models;
			}
			else if ($models instanceof Model) {
				$modelsCollection = new Collection([$models]);
			}
			else {
				return new Response([], static::successfulHttpStatusCode ($this->request->getMethod()));
			}
			
			if ($loadRelations) {
				$this->loadRelatedModels($modelsCollection);
			}
			
			$response = new Response($modelsCollection, static::successfulHttpStatusCode ($this->request->getMethod()));
			$response->links = $links;
			$response->included = ModelsUtils::getIncludedModels ($modelsCollection);;

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
				$model->loadRelatedModels($this->exposedRelationsFromRequest());
			}
		}

		/**
		 * @return Model|Collection
		 */
		private function handleRequest () {
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
					if ($model instanceof Model === true) {
						$model->loadRelatedModels ($this->exposedRelationsFromRequest());
					}
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
					QueryFilter::sortRequest($this->request, $query);
					$this->applyFilters ($query);
					
					//This method will execute get function inside paginate () or if not pagination present, inside itself.
					$model = QueryFilter::paginateRequest($this->request, $query);
					
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
		public function handlePost () {
			$this->beforeHandlePost ();
			$this->requestType = static::POST;
			
			$modelName = $this->fullModelName;
			$data = $this->parseRequestContent ();
			StringUtils::normalizeAttributes($data ["attributes"]);
			
			$attributes = $data ["attributes"];
			
			/** @var Model $model */
			$model = new $modelName($attributes);
			if (empty($model) === true) {
				throw new Exception(
					[
						new Error ('An unknown error occurred', Error::UNKNOWN_ERROR,
							Response::HTTP_INTERNAL_SERVER_ERROR, static::ERROR_SCOPE
						)
				    ]
				);
			}
			
			$model->validateUserCreatePermissions ($this->request, Auth::user ());
			$model->validateData($attributes);
			
			//Update relationships twice, first to update belongsTo and then to update polymorphic and others
			$model->updateRelationships ($data, $this->modelsNamespace, true);
			
			$this->beforeSaveNewModel ($model);
			$this->saveModel($model);
			$this->afterSaveNewModel ($model);
			
			$model->updateRelationships ($data, $this->modelsNamespace, true);
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
		public function handlePatch () {
			$this->beforeHandlePatch ();
			$this->requestType = static::PATCH;
			
			$data = $this->parseRequestContent (false);
			$id = $data["id"];

			$modelName = $this->fullModelName;
			/** @var \EchoIt\JsonApi\Database\Eloquent\Model $model */
			$model = $modelName::find ($id);
			
			if (is_null ($model) === true) {
				throw new Exception
				(
					[
						new Error ('Record not found in Database', Error::UNKNOWN_ERROR, Response::HTTP_NOT_FOUND,
							static::ERROR_SCOPE
						)
					]
				);
			}
			
			$model->validateUserUpdatePermissions ($this->request, Auth::user ());
			
			$originalAttributes = $model->getOriginal ();

			if (array_key_exists ("attributes", $data)) {
				StringUtils::normalizeAttributes($data ["attributes"]);
				$attributes = $data ["attributes"];
				
				$model->fill ($attributes);
				$model->validateData ($attributes);
			}

			$model->updateRelationships ($data, $this->modelsNamespace, false);

			$this->beforeSaveModel ($model);
			$this->saveModel($model);
			$this->afterSaveModel ($model);
			
			$this->verifyIfModelChanged ($model, $originalAttributes);

			if ($model->isChanged()) {
				CacheManager::clearCache (StringUtils::dasherizedResourceName($this->resourceName), $id, $model);
			}
			
			$this->afterHandlePatch ($model);
			
			return $model;
		}

		public function handlePut () {
			return $this->handlePatch ();
		}

		/**
		 * Handle DELETE requests
		 *
		 * @return \EchoIt\JsonApi\Database\Eloquent\Model
		 * @throws \EchoIt\JsonApi\Exception
		 */
		public function handleDelete () {
			$this->beforeHandleDelete ();
			$this->requestType = static::DELETE;
			
			if (empty($this->request->getId())) {
				throw new Exception (
					[
						new Error ('No ID provided', Error::NO_ID, Response::HTTP_BAD_REQUEST, static::ERROR_SCOPE)
					]
				);
			}
			
			$modelName = $this->fullModelName;
			
			/** @var Model $model */
			$model = $modelName::find ($this->request->getId());
			
			$model->validateUserDeletePermissions ($this->request, Auth::user ());
			
			if (is_null ($model)) {
				return null;
			}
			
			$model->delete ();
			
			$this->afterHandleDelete ($model);
			return $model;
		}
		
		/**
		 * Parses content from request into an array of values.
		 *
		 * @param  string $content
		 * @param bool    $newRecord
		 *
		 * @return array
		 * @throws \EchoIt\JsonApi\Exception
		 * @internal param string $type the type the content is expected to be.
		 */
		protected function parseRequestContent ($newRecord = true) {
			$content = $this->request->getContent();
			$content = json_decode ($content, true);

			if (isset ($content) === false || is_array($content) === false || array_key_exists('data', $content) === false) {
				throw new Exception(
					[
						new Error ('Payload either contains misformed JSON or missing "data" parameter.',
							Error::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST, static::ERROR_SCOPE
						)
					]
				);
			}
			$data = $content['data'];
			
			if (array_key_exists ("type", $data) === false) {
				throw new Exception(
					[
						new Error ('"type" parameter not set in request.',
							Error::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST, static::ERROR_SCOPE
						)
					]
				);
			}
			if ($data['type'] !== $type = Pluralizer::plural (s ($this->resourceName)->dasherize ()->__toString ())) {
				throw new Exception(
					[
						new Error ('"type" parameter is not valid. Expecting ' . $type,
							Error::INVALID_ATTRIBUTES, Response::HTTP_CONFLICT, static::ERROR_SCOPE
						)
					]
				);
			}
			if ($newRecord === false && isset($data['id']) === false) {
				throw new Exception(
					[
						new Error ('"id" parameter not set in request.',
							Error::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST, static::ERROR_SCOPE
						)
					]
				);
			}
			
			unset ($content ['type']);
			
			return $data;
		}
		
		/**
		 * @param Model $model
		 * @param $originalAttributes
		 */
		public function verifyIfModelChanged (Model $model, $originalAttributes) {
			// fetch the current attributes (post save)
			$newAttributes = $model->getAttributes ();
			
			// loop through the new attributes, and ensure they are identical
			// to the original ones. if not, then we need to return the model
			foreach ($newAttributes as $attribute => $value) {
				if (array_key_exists ($attribute, $originalAttributes) === false ||
					$value !== $originalAttributes[$attribute]
				) {
					$model->markChanged ();
					break;
				}
			}
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
		 * Returns which requested resources are available to include.
		 *
		 * @return array
		 */
		protected function exposedRelationsFromRequest() {
			$include = $this->request->input('include');
			if (is_null($include)) {
				return [];
			}
			return explode(",", $include);
		}

		/**
		 * Returns which of the requested resources are not available to include.
		 *
		 * @return array
		 */
		protected function unknownRelationsFromRequest() {
			return array_diff($this->request->getInclude(), static::$exposedRelations);
		}

		/**
		 * Return pagination links as array
		 * @param LengthAwarePaginator $paginator
		 * @return array
		 */
		protected function getPaginationLinks(LengthAwarePaginator $paginator) {
			$links = [];

			$links['self'] = urldecode($paginator->url($paginator->currentPage()));
			$links['first'] = urldecode($paginator->url(1));
			$links['last'] = urldecode($paginator->url($paginator->lastPage()));

			$links['prev'] = urldecode($paginator->url($paginator->currentPage() - 1));
			if ($links['prev'] === $links['self'] || $links['prev'] === '') {
				$links['prev'] = null;
			}
			$links['next'] = urldecode($paginator->nextPageUrl());
			if ($links['next'] === $links['self'] || $links['next'] === '') {
				$links['next'] = null;
			}
			return $links;
		}

		/**
		 * Return errors which did not prevent the API from returning a result set.
		 *
		 * @return array
		 */
		protected function getNonBreakingErrors() {
			$errors = [];

			$unknownRelations = $this->unknownRelationsFromRequest();
			if (count($unknownRelations) > 0) {
				$errors[] = [
					'code' => Error::UNKNOWN_LINKED_RESOURCES,
					'title' => 'Unknown included resource requested',
					'description' => 'These included resources are not available: ' . implode(', ', $unknownRelations)
				];
			}

			return $errors;
		}

		/**
		 * A method for getting the proper HTTP status code for a successful request
		 *
		 * @param  string $method "PUT", "POST", "DELETE" or "GET"
		 * @param  Model|null $model The model that a PUT request was executed against
		 * @return int
		 */
		public static function successfulHttpStatusCode($method, $model = null) {
			// if we did a put request, we need to ensure that the model wasn't
			// changed in other ways than those specified by the request
			//     Ref: http://jsonapi.org/format/#crud-updating-responses-200
			if (($method === 'PATCH' || $method === 'PUT') && $model instanceof Model) {
				// check if the model has been changed
				if ($model->isChanged()) {
					// return our response as if there was a GET request
					$method = 'GET';
				}
			}

			switch ($method) {
				case 'POST':
					return Response::HTTP_CREATED;
				case 'PATCH':
				case 'PUT':
				case 'DELETE':
					return Response::HTTP_NO_CONTENT;
				case 'GET':
					return Response::HTTP_OK;
			}

			// Code shouldn't reach this point, but if it does we assume that the
			// client has made a bad request.
			return Response::HTTP_BAD_REQUEST;
		}
		
		/**
		 * Generates model names from controller name class
		 */
		private function generateModelName () {
			$shortName = $this->resourceName;
			$this->shortModelName = Model::getModelClassName ($shortName, $this->modelsNamespace, true, true);
			$this->fullModelName = Model::getModelClassName ($shortName, $this->modelsNamespace, true);
		}
		
		/**
		 * Generates resource name from class name
		 */
		private function setResourceName () {
			$shortClassName = ClassUtils::getControllerShortClassName(get_called_class());
			$resourceNameLength = strlen($shortClassName) - self::CONTROLLER_WORD_LENGTH;
			$this->resourceName = substr ($shortClassName, 0, $resourceNameLength);
		}
		
		/**
		 * @return boolean
		 */
		protected function allowsModifyingByAllUsers () {
			$modelName = $this->fullModelName;
			return $modelName::allowsModifyingByAllUsers ();
		}
		
		/**
		 * Generates a find query from model name
		 *
		 * @return Builder|null
		 */
		protected function generateSelectQuery() {
			$modelName = $this->fullModelName;
			//If this model has any relation, eager load
			if (count (static::$exposedRelations) > 0) {
				//Call static function with
				return forward_static_call_array ([$modelName, 'with'], static::$exposedRelations);
			}
			//If this model doesn't have any relations, generate a new empty query
			else {
				return forward_static_call_array ([$modelName, 'queryAllModels'], []);
			}
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
		 */
		protected function afterHandleGet (Model $model) {
			
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
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		protected function afterGeneratePostResponse (Model $model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a PATCH request. Should be implemented by child classes.
		 */
		protected function beforeHandlePatch () {
			
		}
		
		/**
		 * Method that runs after handling a PATCH request. Should be implemented by child classes.
		 */
		protected function afterHandlePatch (Model $model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		protected function afterGeneratePatchResponse (Model $model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a DELETE request. Should be implemented by child classes.
		 */
		protected function beforeHandleDelete () {
			
		}
		
		/**
		 * Method that runs after handling a DELETE request. Should be implemented by child classes.
		 */
		protected function afterHandleDelete (Model $model) {
			
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
		 * @param $model
		 *
		 * @throws Exception
		 */
		protected function saveModel(Model $model) {
			try {
				$model->saveOrFail();
			} catch (QueryException $exception) {
				throw new Exception(
					[
						new SqlError ('Database error', Error::DATABASE_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR,
							$exception, static::ERROR_SCOPE)
					]
				);
			} catch (\Exception $exception) {
				throw new Exception(
					[
						new Error ('An unknown error occurred saving the record', Error::UNKNOWN_ERROR,
							Response::HTTP_INTERNAL_SERVER_ERROR, static::ERROR_SCOPE)
					]
				);
			}
		}
	}
