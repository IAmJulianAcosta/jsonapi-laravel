<?php

	namespace EchoIt\JsonApi;
	
	use EchoIt\JsonApi\Database\Eloquent\Model;
	use EchoIt\JsonApi\Exception;
	use EchoIt\JsonApi\Cache\CacheManager;
	use EchoIt\JsonApi\Http\Response;
	use EchoIt\JsonApi\Http\Request;
	use EchoIt\JsonApi\Utils\ClassUtils;
	use EchoIt\JsonApi\Utils\ModelsUtils;
	use EchoIt\JsonApi\Utils\StringUtils;
	use Illuminate\Database\QueryException;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Support\Collection;
	use Illuminate\Support\Pluralizer;
	use Illuminate\Pagination\LengthAwarePaginator;
	use Illuminate\Pagination\Paginator;
	use function Stringy\create as s;
	use Cache;

	abstract class Handler {

		/**
		 * Override this const in the extended to distinguish model handlers from each other.
		 *
		 * See under default error codes which bits are reserved.
		 */
		const ERROR_SCOPE = 0;

		/**
		 * Default error codes.
		 */
		const ERROR_UNKNOWN_ID = 1;
		const ERROR_UNKNOWN_LINKED_RESOURCES = 2;
		const ERROR_NO_ID = 4;
		const ERROR_INVALID_ATTRS = 8;
		const ERROR_HTTP_METHOD_NOT_ALLOWED = 16;
		const ERROR_ID_PROVIDED_NOT_ALLOWED = 32;
		const ERROR_MISSING_DATA = 64;
		const ERROR_UNKNOWN = 128;
		const ERROR_RESERVED_8 = 256;
		const ERROR_RESERVED_9 = 512;
		
		const HANDLER_WORD_LENGTH = 7;
		const ERROR_UNAUTHORIZED = 256;
		
		protected static $namespace;
		protected static $exposedRelations;
		
		/**
		 * @var integer request type. Is set before fulfilling each kind of request.
		 */
		protected $requestType;
		
		const GET = 0;
		const GET_ALL = 1;
		const POST = 2;
		const PATCH = 3;
		const DELETE = 4;
		
		/**
		 * @var Model Class name used by this handler including namespace
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
		 * @var string Resource name handler
		 */
		protected $resourceName;
		
		protected $modelsNamespace;
		
		/**
		 * Supported methods for handler. Override to limit available methods.
		 *
		 * @var array
		 */
		protected $supportedMethods = ["get", "post", "put", "patch", "delete"];
		
		/**
		 * @var Request
		 */
		protected $request;
		
		/**
		 * BaseHandler constructor. Defines modelName based of HandlerName
		 *
		 * @param Request $request
		 * @param         $modelsNamespace
		 */
		public function __construct (Request $request, $modelsNamespace) {
			$this->request = $request;
			$this->modelsNamespace = $modelsNamespace;
			$this->setResourceName ();
			$this->generateModelName ();
		}

		/**
		 * Fulfill the API request and return a response. This is the entrypoint of handler, and should be called from
		 * controller.
		 *
		 * @return Response
		 * @throws Exception
		 */
		public function fulfillRequest () {
			$request = $this->request;
			$this->beforeFulfillRequest($request);
			$httpMethod = $request->getMethod ();

			if (!$this->supportsMethod ($httpMethod)) {
				throw new Exception(
					[
						new Error (
							'Method not allowed',
							static::ERROR_SCOPE | static::ERROR_HTTP_METHOD_NOT_ALLOWED,
							Response::HTTP_METHOD_NOT_ALLOWED
						)
				    ]
				);
			}

			//Validates if this resource could be updated/deleted/created by all users.
			if ($httpMethod !== 'GET' && $this->allowsModifyingByAllUsers () === false) {
				throw new Exception(
					[
						new Error (
							'This user cannot modify this resource',
							static::ERROR_SCOPE | static::ERROR_UNKNOWN | static::ERROR_UNAUTHORIZED,
							Response::HTTP_FORBIDDEN
						)
					]
				);
			}
			
			//Executes the request
			$this->beforeHandleRequest($request);
			$model = $this->handleRequest ($request);
			$this->afterHandleRequest($request, $model);

			if (is_null ($model)) {
				throw new Exception(
					[
						new Error (
							'Unknown ID',
							static::ERROR_SCOPE | static::ERROR_UNKNOWN_ID,
							Response::HTTP_NOT_FOUND
						)
				    ]
				);
			}

			$this->beforeGenerateResponse($request, $model);
			if ($httpMethod === 'GET') {
				$response = $this->generateCacheableResponse ($model, $request);
			}
			else {
				if ($model instanceof Model) {
					$response = $this->generateNonCacheableResponse ($model);
				}
			}
			$this->afterGenerateResponse($request, $model, $response);
			return $response;
		}
		
		/**
		 * Fullfills GET requests
		 *
		 * @param $models
		 * @param $request
		 *
		 * @return mixed
		 */
		private function generateCacheableResponse ($models, Request $request) {
			$id = $request->getId();
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
					return $this->generateResponse($models, false);
				}
			);
		}
		
		/**
		 * Fullfills POST, PATCH and DELETE requests
		 *
		 * @param \Illuminate\Http\Request $models
		 *
		 * @return \EchoIt\JsonApi\Http\Response
		 *
		 */
		private function generateNonCacheableResponse ($models) {
			return $this->generateResponse($models);
		}
		
		/**
		 * @param $models
		 * @param $loadRelations
		 * @return JsonResponse
		 */
		private function generateResponse ($models, $loadRelations = true) {
			if ($models instanceof Response) {
				$response = $models;
			}
			elseif ($models instanceof LengthAwarePaginator) {
				$items = new Collection($models->items ());
				foreach ($items as $model) {
					if ($loadRelations) {
						$model->loadRelatedModels ($this->exposedRelationsFromRequest($model));
					}
				}
				$response = new Response($items, static::successfulHttpStatusCode ($this->request->getMethod()));
				$response->links = $this->getPaginationLinks ($models);
				$response->included = $this->getIncludedModels ($items);
				$response->errors = $this->getNonBreakingErrors ();
			}
			else {
				if ($models instanceof Collection) {
					/** @var Model $model */
					foreach ($models as $model) {
						if ($loadRelations) {
							$model->loadRelatedModels ($this->exposedRelationsFromRequest());
						}
					}
				}
				else if ($models instanceof Model){
					$model = $models;
					if ($loadRelations) {
						$model->loadRelatedModels ($this->exposedRelationsFromRequest());
					}
				}

				$response = new Response($models, static::successfulHttpStatusCode ($this->request->getMethod(), $models));

				$response->included = ModelsUtils::getIncludedModels($models);
				$response->errors = $this->getNonBreakingErrors ();
			}

			return $response;
		}

		/**
		 * @return Model|Collection
		 */
		private function handleRequest (Request $request) {
			$methodName = ClassUtils::methodHandlerName($request->getMethod());
			$models = $this->{$methodName}($request);

			return $models;
		}
		
		/**
		 * @param Request $request
		 *
		 * @return Model|Collection|null
		 */
		protected function handleGet (Request $request) {
			$id = $request->getId();
			if (empty($id)) {
				$models = $this->handleGetAll ($request);
				return $models;
			}
			else {
				return $this->handleGetSingle($request, $id);
			}
		}
		
		/**
		 * @param Request $request
		 * @param         $id
		 *
		 * @return mixed
		 */
		protected function handleGetSingle(Request $request, $id) {
			$this->beforeHandleGet($request);
			$this->requestType = static::GET;
			
			$key = CacheManager::getQueryCacheForSingleResource($id, StringUtils::dasherizedResourceName($this->resourceName));
			
			$model     = Cache::remember(
				$key,
				static::$cacheTime,
				function () use ($request) {
					$query = $this->generateSelectQuery ();
					
					$query->where('id', $request->getId());
					$model = $query->first ();
					if ($model instanceof Model) {
						$model->loadRelatedModels ($this->exposedRelationsFromRequest());
					}
					return $model;
				}
			);
			$this->afterHandleGet ($request, $model);

			return $model;
		}

		/**
		 * @param Request $request
		 *
		 * @return Collection
		 */
		protected function handleGetAll (Request $request) {
			$this->beforeHandleGetAll ($request);
			$this->requestType = static::GET_ALL;
			
			$key = CacheManager::getQueryCacheForMultipleResources(StringUtils::dasherizedResourceName($this->resourceName));
			$models = Cache::remember (
				$key, static::$cacheTime,
				function () use ($request) {
					$query = $this->generateSelectQuery ();
					
					QueryFilter::filterRequest($request, $query);
					QueryFilter::sortRequest($request, $query);
					$this->applyFilters ($request, $query);
					$model = $query->get ();
					return $model;
				}
			);
			$this->afterHandleGetAll ($request, $models);
			return $models;
		}

		/**
		 * Handle POST requests
		 *
		 * @param Request $request
		 *
		 * @return Model
		 * @throws Exception
		 * @throws Exception\ValidationException
		 */
		public function handlePost (Request $request) {
			$this->beforeHandlePost ($request);
			$this->requestType = static::POST;
			
			$modelName = $this->fullModelName;
			$data = $this->parseRequestContent ($request->getContent());
			$this->normalizeAttributes ($data ["attributes"]);
			
			$attributes = $data ["attributes"];
			
			/** @var Model $model */
			$model = forward_static_call_array ([$modelName, 'createNew'], [$attributes]);
			if (is_null($model) === true) {
				throw new Exception(
					[
						new Error (
							'An unknown error occurred',
							static::ERROR_SCOPE | static::ERROR_UNKNOWN,
							Response::HTTP_INTERNAL_SERVER_ERROR
						)
				    ]
				);
			}
			//Update relationships twice, first to update belongsTo and then to update polymorphic and others
			$model->updateRelationships ($data, $this->modelsNamespace, true);

			$model->validateData ($attributes);

			try {
				if (!$model->saveOrFail ()) {
					throw new Exception(
						[
							new Error(
								'An unknown error occurred saving the record',
								static::ERROR_SCOPE | static::ERROR_UNKNOWN,
								Response::HTTP_INTERNAL_SERVER_ERROR
							)
						]
					);
				}
			}
			catch (QueryException $e) {
				throw new Exception(
					[
						new Error (
							'Database error',
							static::ERROR_SCOPE | static::ERROR_UNKNOWN,
							Response::HTTP_INTERNAL_SERVER_ERROR
						)
					]
				);
			}
			catch (\Exception $e) {
				throw new Exception(
					[
						new Error (
							'An unknown error occurred saving the record',
							static::ERROR_SCOPE | static::ERROR_UNKNOWN,
							Response::HTTP_INTERNAL_SERVER_ERROR
						)
					]
				);
			}

			$model->updateRelationships ($data, $this->modelsNamespace, true);
			$model->markChanged ();
			CacheManager::clearCache(StringUtils::dasherizedResourceName($this->resourceName));
			$this->afterHandlePost ($request, $model);

			return $model;
		}
		
		/**
		 * Handle PATCH requests
		 *
		 * @param Request $request
		 *
		 * @return Model|null
		 * @throws Exception
		 */
		public function handlePatch (Request $request) {
			$this->beforeHandlePatch ($request);
			$this->requestType = static::PATCH;
			
			$data = $this->parseRequestContent ($request->getContent(), false);
			$id = $data["id"];

			$modelName = $this->fullModelName;
			/** @var \EchoIt\JsonApi\Database\Eloquent\Model $model */
			$model = $modelName::find ($id);
			
			if (is_null ($model)) {
				//TODO throw exception
				return null;
			}
			
			$this->verifyUserPermission($request, $model);
			
			$originalAttributes = $model->getOriginal ();

			if (array_key_exists ("attributes", $data)) {
				$this->normalizeAttributes ($data ["attributes"]);
				$attributes = $data ["attributes"];
				
				$model->fill ($attributes);
				$model->validateData ($attributes);
			}

			$model->updateRelationships ($data, $model, false);

			// ensure we can get a successful save
			if (!$model->save ()) {
				throw new Exception
				(
					[
						new Error (
							'An unknown error occurred',
							static::ERROR_SCOPE | static::ERROR_UNKNOWN,
							Response::HTTP_INTERNAL_SERVER_ERROR
						)
					]
				);
			}

			$this->verifyIfModelChanged ($model, $originalAttributes);

			if ($model->isChanged()) {
				CacheManager::clearCache (StringUtils::dasherizedResourceName($this->resourceName), $id, $model);
			}
			
			$this->afterHandlePatch ($request, $model);
			
			return $model;
		}

		public function handlePut (Request $request) {
			return $this->handlePatch ($request);
		}

		/**
		 * Handle DELETE requests
		 *
		 * @param  \EchoIt\JsonApi\Request $request
		 *
		 * @return \EchoIt\JsonApi\Database\Eloquent\Model
		 * @throws \EchoIt\JsonApi\Exception
		 */
		public function handleDelete (Request $request) {
			$this->beforeHandleDelete ($request);
			$this->requestType = static::DELETE;
			
			if (empty($request->getId())) {
				throw new Exception (
					[
						new Error (
							'No ID provided',
							static::ERROR_SCOPE | static::ERROR_NO_ID,
							Response::HTTP_BAD_REQUEST
						)
					]
				);
			}
			
			$modelName = $this->fullModelName;
			
			/** @var Model $model */
			$model = $modelName::find ($request->getId());

			$this->verifyUserPermission($request, $model);
			
			if (is_null ($model)) {
				return null;
			}
			
			$model->delete ();
			
			$this->afterHandleDelete ($request, $model);
			return $model;
		}

		/**
		 * @param array $attributes
		 * @return array
		 */
		private function normalizeAttributes (array &$attributes) {
			foreach ($attributes as $key => $value) {
				if (is_string ($key)) {
					unset ($attributes[$key]);
					$attributes[ s( $key )->underscored()->__toString() ] = $value;
				}
			}
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
		protected function parseRequestContent ($content, $newRecord = true) {
			$content = json_decode ($content, true);

			if (array_key_exists('data', $content) === false) {
				throw new Exception(
					[
						new Error (
							'Payload either contains misformed JSON or missing "data" parameter.',
							static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS, Response::HTTP_BAD_REQUEST
						)
					]
				);
			}
			$data = $content['data'];
			
			if (array_key_exists ("type", $data) === false) {
				throw new Exception(
					[
						new Error (
							'"type" parameter not set in request.',
							static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
							Response::HTTP_BAD_REQUEST
						)
					]
				);
			}
			if ($data['type'] !== $type = Pluralizer::plural (s ($this->resourceName)->dasherize ()->__toString ())) {
				throw new Exception(
					[
						new Error (
							'"type" parameter is not valid. Expecting ' . $type,
							static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
							Response::HTTP_CONFLICT
						)
					]
				);
			}
			if ($newRecord === false && isset($data['id']) === false) {
				throw new Exception(
					[
						new Error (
							'"id" parameter not set in request.',
							static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
							Response::HTTP_BAD_REQUEST
						)
					]
				);
			}
			
			unset ($content ['type']);
			
			return $data;
		}
		
		/**
		 * @param Model $model
		 * @param               $originalAttributes
		 *
		 * @return \EchoIt\JsonApi\Database\Eloquent\Model
		 *
		 */
		public function verifyIfModelChanged (Model $model, $originalAttributes) {
			// fetch the current attributes (post save)
			$newAttributes = $model->getAttributes ();
			
			// loop through the new attributes, and ensure they are identical
			// to the original ones. if not, then we need to return the model
			foreach ($newAttributes as $attribute => $value) {
				if (array_key_exists ($attribute, $originalAttributes === false) ||
					$value !== $originalAttributes[$attribute]
				) {
					$model->markChanged ();
					break;
				}
			}
		}
		
		/**
		 * @param Request $request
		 * @param         $model
		 *
		 * @throws Exception
		 */
		abstract protected function verifyUserPermission( Request $request, $model );

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
					'code' => static::ERROR_UNKNOWN_LINKED_RESOURCES,
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
		 * This method returns the value from given array and key, and will create a
		 * new Collection instance on the key if it doesn't already exist
		 *
		 * @param  array &$array
		 * @param  string $key
		 * @return \Illuminate\Database\Eloquent\Collection
		 */
		protected static function getCollectionOrCreate(&$array, $key) {
			if (array_key_exists($key, $array)) {
				return $array[$key];
			}
			return ($array[$key] = new Collection);
		}

		/**
		 * The return value of this method will be used as the key to store the
		 * linked or included model from a relationship. Per default it will return the plural
		 * version of the relation name.
		 * Override this method to map a relation name to a different key.
		 *
		 * @param  string $relationName
		 * @return string
		 */
		protected static function getModelNameForRelation($relationName) {
			return \str_plural($relationName);
		}
		
		/**
		 * Function to handle pagination requests.
		 *
		 * @param  \EchoIt\JsonApi\Request                 $request
		 * @param  \EchoIt\JsonApi\Database\Eloquent\Model $model
		 * @param integer                                  $total the total number of records
		 *
		 * @return \Illuminate\Pagination\LengthAwarePaginator
		 */
		protected function handlePaginationRequest(Request $request, Model $model, $total = null) {
			$page = $request->getPageNumber();
			$perPage = $request->getPageSize();
			if (!$total) {
				$total = $model->count();
			}
			$results = $model->forPage($page, $perPage)->get(array('*'));
			$paginator = new LengthAwarePaginator($results, $total, $perPage, $page, [
				'path' => Paginator::resolveCurrentPath(),
				'pageName' => 'page[number]'
			]);
			$paginator->appends('page[size]', $perPage);
			if (!empty($request->getFilter())) {
				foreach ($request->getFilter() as $key=>$value) {
					$paginator->appends($key, $value);
				}
			}
			if (!empty($request->getSort())) {
				$paginator->appends('sort', implode(',', $request->getSort()));
			}

			return $paginator;
		}
		
		/**
		 * Generates model names from handler name class
		 */
		private function generateModelName () {
			$shortName = $this->resourceName;
			$this->shortModelName = Model::getModelClassName ($shortName, $this->modelsNamespace, true, true);
			$this->fullModelName = Model::getModelClassName ($shortName, $this->modelsNamespace, true);
		}
		
		/**
		 * Generates resource name from class name (ResourceHandler -> resource)
		 */
		private function setResourceName () {
			$shortClassName = ClassUtils::getHandlerShortClassName(get_called_class());
			$resourceNameLength = $shortClassName - self::HANDLER_WORD_LENGTH;
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
		 * @param $modelName
		 * @param $request
		 *
		 * @return \Illuminate\Database\Eloquent\Builder|null
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
		protected abstract function applyFilters ($request, &$query);
		
		
		/**
		 * Method that runs before fullfilling a request. Should be implemented by child classes.
		 */
		protected function beforeFulfillRequest (Request $request) {
			
		}
		
		/**
		 * Method that runs before handling a request. Should be implemented by child classes.
		 */
		protected function beforeHandleRequest (Request $request) {
			
		}
		
		/**
		 * Method that runs after handling a request. Should be implemented by child classes.
		 */
		protected function afterHandleRequest (Request $request, $models) {
			
		}
		
		/**
		 * Method that runs before generating the response. Should be implemented by child classes.
		 */
		protected function beforeGenerateResponse (Request $request) {
			
		}
		
		/**
		 * Method that runs after generating the response. Shouldn't be overriden by child classes.
		 */
		protected function afterGenerateResponse (Request $request, $model, Response $response) {
			switch ($this->requestType) {
				case static::GET;
					$this->afterGenerateGetResponse ($request, $model, $response);
					break;
				case static::GET_ALL;
					$this->afterGenerateGetAllResponse($request, $model, $response);
					break;
				case static::POST;
					$this->afterGeneratePostResponse($request, $model, $response);
					break;
				case static::PATCH;
					$this->afterGeneratePatchResponse($request, $model, $response);
					break;
				case static::DELETE;
					$this->afterGenerateDeleteResponse($request, $model, $response);
					break;
			}
		}
		
		/**
		 * Method that runs before handling a GET request. Should be implemented by child classes.
		 */
		protected function beforeHandleGet (Request $request) {
			
		}
		
		/**
		 * Method that runs after handling a GET request. Should be implemented by child classes.
		 */
		protected function afterHandleGet (Request $request, Model $model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response. Should be implemented by child classes.
		 */
		protected function afterGenerateGetResponse (Request $request, $model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a GET request of all resources. Should be implemented by child classes.
		 */
		protected function beforeHandleGetAll (Request $request) {
			
		}
		
		/**
		 * Method that runs after handling a GET request of all resources. Should be implemented by child classes.
		 */
		protected function afterHandleGetAll (Request $request, Collection $models) {
			
		}
		
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		protected function afterGenerateGetAllResponse (Request $request, $model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a POST request. Should be implemented by child classes.
		 */
		protected function beforeHandlePost (Request $request) {
			
		}
		
		/**
		 * Method that runs after handling a POST request. Should be implemented by child classes.
		 */
		protected function afterHandlePost (Request $request, Model $model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		protected function afterGeneratePostResponse (Request $request, $model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a PATCH request. Should be implemented by child classes.
		 */
		protected function beforeHandlePatch (Request $request) {
			
		}
		
		/**
		 * Method that runs after handling a PATCH request. Should be implemented by child classes.
		 */
		protected function afterHandlePatch (Request $request, Model $model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		protected function afterGeneratePatchResponse (Request $request, $model, Response $response) {
			
		}
		
		/**
		 * Method that runs before handling a DELETE request. Should be implemented by child classes.
		 */
		protected function beforeHandleDelete (Request $request) {
			
		}
		
		/**
		 * Method that runs after handling a DELETE request. Should be implemented by child classes.
		 */
		protected function afterHandleDelete (Request $request, Model $model) {
			
		}
		
		/**
		 * Method that runs after generating a GET response of all resources. Should be implemented by child classes.
		 */
		protected function afterGenerateDeleteResponse (Request $request, $model, Response $response) {
			
		}
		
	}
