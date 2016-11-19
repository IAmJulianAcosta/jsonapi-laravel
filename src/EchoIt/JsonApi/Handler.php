<?php

	namespace EchoIt\JsonApi;
	
	use EchoIt\JsonApi\Exception;
	use EchoIt\JsonApi\CacheManager;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Support\Collection;
	use Illuminate\Http\Response as BaseResponse;
	use Illuminate\Support\Pluralizer;
	use Illuminate\Pagination\LengthAwarePaginator;
	use Illuminate\Pagination\Paginator;
	use function Stringy\create as s;
	use Cache;
	use Illuminate\Support\Facades\DB;

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
		 * @return \EchoIT\JsonApi\Response
		 * @throws Exception
		 */
		public function fulfillRequest () {
			$request = $this->request;
			$httpMethod = $request->method;

			if (!$this->supportsMethod ($httpMethod)) {
				throw new Exception(
					'Method not allowed',
					static::ERROR_SCOPE | static::ERROR_HTTP_METHOD_NOT_ALLOWED,
					BaseResponse::HTTP_METHOD_NOT_ALLOWED
				);
			}

			/*
			 * Validates if this resource could be updated/deleted/created by all users.
			 */
			if ($httpMethod !== 'GET' && $this->allowsModifyingByAllUsers () === false) {
				throw new Exception('This user cannot modify this resource',
					static::ERROR_SCOPE | static::ERROR_UNKNOWN | static::ERROR_UNAUTHORIZED,
					BaseResponse::HTTP_FORBIDDEN);
			}

			/*
			 * Executes the request
			 */
			$models = $this->handleRequest ($request);

			if (is_null ($models)) {
				throw new Exception(
					'Unknown ID', static::ERROR_SCOPE | static::ERROR_UNKNOWN_ID, BaseResponse::HTTP_NOT_FOUND
				);
			}

			if ($httpMethod === 'GET') {
				return $this->generateCacheableResponse ($models, $request);
			}
			else {
				if ($models instanceof Model) {
					return $this->generateNonCacheableResponse ($models);
				}
			}
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
			$id = $request->id;
			if (empty($id)) {
				$key = CacheManager::getResponseCacheForMultipleResources($this->dasherizedResourceName());
			}
			else {
				$key = CacheManager::getResponseCacheForSingleResource($id, $this->dasherizedResourceName());
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
		 * @return \EchoIt\JsonApi\Response
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
						$this->loadRelatedModels ($model);
					}
				}

				$response = new Response($items, static::successfulHttpStatusCode ($this->request->method));

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
					if ($loadRelations) {
						$model->loadRelatedModels ($this->exposedRelationsFromRequest());
					}
				}

				$response = new Response($models, static::successfulHttpStatusCode ($this->request->method, $models));

				$response->included = $this->getIncludedModels ($models);
				$response->errors = $this->getNonBreakingErrors ();
			}

			return $response->toJsonResponse();
		}

		/**
		 * @return Model|Collection
		 */
		private function handleRequest (Request $request) {
			$methodName = Utils::methodHandlerName($request->method);
			$models = $this->{$methodName}($request);

			return $models;
		}
		
		/**
		 * @param Request $request
		 *
		 * @return Model|Collection|null
		 */
		public function handleGet (Request $request) {
			$id = $request->id;
			if (empty($id)) {
				$models = $this->handleGetAll ();

				return $models;
			}

			$modelName = $this->fullModelName;
			$key = CacheManager::getQueryCacheForSingleResource($id, $this->dasherizedResourceName());
			$model = Cache::remember (
				$key, static::$cacheTime,
				function () use ($modelName, $request) {
					$model = $modelName::find ($request->id);
					if ($model) {
						$this->loadRelatedModels ($model);
					}
					return $model;
				}
			);

			return $model;
		}

		/**
		 * @param Request $request
		 *
		 * @return Collection
		 */
		protected function handleGetAll () {
			$key = CacheManager::getQueryCacheForMultipleResources($this->dasherizedResourceName());
			$modelName = $this->fullModelName;
			$models = Cache::remember (
				$key, static::$cacheTime,
				function () use ($modelName) {
					if (count (static::$exposedRelations) > 0) {
						$query = forward_static_call_array (array ($modelName, 'with'), static::$exposedRelations);
					}
					else {
						$query = DB::table (Pluralizer::plural ($modelName));
					}
					QueryFilter::handleFilterRequest($request, Pluralizer::plural ($modelName));
					$query->get ();
				}
			);

			return $models;
		}

		/**
		 * Handle POST requests
		 *
		 * @param Request $request
		 *
		 * @return Model
		 * @throws Exception
		 * @throws Exception\Validation
		 */
		public function handlePost (Request $request) {
			$modelName = $this->fullModelName;
			$data = $this->parseRequestContent ($request->content);
			$this->normalizeAttributes ($data ["attributes"]);
			
			$attributes = $data ["attributes"];
			
			/** @var Model $model */
			$model = new $modelName ($attributes);
			
			//Update relationships twice, first to update belongsTo and then to update polymorphic and others
			$model->updateRelationships ($data, $model, true);
			$model->validateData ($attributes);

			if (!$model->save ()) {
				throw new Exception(
					'An unknown error occurred', static::ERROR_SCOPE | static::ERROR_UNKNOWN,
					BaseResponse::HTTP_INTERNAL_SERVER_ERROR);
			}

			$model->updateRelationships ($data, $model, true);
			$model->markChanged ();
			CacheManager::clearCache($this->dasherizedResourceName());

			return $model;
		}
		
		/**
		 * Handle PATCH requests
		 *
		 * @param \EchoIt\JsonApi\Request $request
		 *
		 * @return \EchoIt\JsonApi\Model|null
		 * @throws \EchoIt\JsonApi\Exception
		 */
		public function handlePatch (Request $request) {
			$data = $this->parseRequestContent ($request->content, false);
			$id = $data["id"];

			$modelName = $this->fullModelName;
			/** @var Model $model */
			$model = $modelName::find ($id);
			
			if (is_null ($model)) {
				return null;
			}
			
			$this->verifyUserPermission($request, $model);
			
			$originalAttributes = $model->getOriginal ();

			if (array_key_exists ("attributes", $data)) {
				$this->normalizeAttributes ($data ["attributes"]);
				$attributes = $data ["attributes"];
				
				$model->fill ($attributes);
				$this->validateData ($attributes);
			}

			$model->updateRelationships ($data, $model, false);

			// ensure we can get a successful save
			if (!$model->save ()) {
				throw new Exception(
					'An unknown error occurred', static::ERROR_SCOPE | static::ERROR_UNKNOWN,
					BaseResponse::HTTP_INTERNAL_SERVER_ERROR);
			}

			$this->verifyIfModelChanged ($model, $originalAttributes);

			if ($model->isChanged()) {
				CacheManager::clearCache ($this->dasherizedResourceName(), $id, $model);
			}
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
		 * @return \EchoIt\JsonApi\Model
		 * @throws \EchoIt\JsonApi\Exception
		 */
		public function handleDelete (Request $request) {
			if (empty($request->id)) {
				throw new Exception(
					'No ID provided', static::ERROR_SCOPE | static::ERROR_NO_ID, BaseResponse::HTTP_BAD_REQUEST);
			}
			
			$modelName = $this->fullModelName;
			
			/** @var Model $model */
			$model = $modelName::find ($request->id);

			$this->verifyUserPermission($request, $model);
			
			if (is_null ($model)) {
				return null;
			}
			
			$model->delete ();
			
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
		 * Iterate through result set to fetch the requested resources to include.
		 *
		 * @param Model $models
		 * @return array
		 */
		protected function getIncludedModels ($models) {
			$links = new Collection();
			$models = $models instanceof Collection ? $models : [$models];
			
			/** @var Model $model */
			foreach ($models as $model) {
				$exposedRelations = $model->exposedRelations();
				
				foreach ($exposedRelations as $relationName) {
					$value = static::getModelsForRelation ($model, $relationName);
					
					if (is_null ($value)) {
						continue;
					}

					//Each one of the models relations
					/* @var Model $obj*/
					foreach ($value as $obj) {
						// Check whether the object is already included in the response on it's ID
						$duplicate = false;
						$items = $links->where ($obj->getPrimaryKey (), $obj->getKey ());
						
						if (count ($items) > 0) {
							foreach ($items as $item) {
								/** @var $item Model */
								if ($item->getResourceType () === $obj->getResourceType ()) {
									$duplicate = true;
									break;
								}
							}
							if ($duplicate) {
								continue;
							}
						}
						
						//add type property
						$attributes = $obj->getAttributes ();
						
						$obj->setRawAttributes ($attributes);
						
						$links->push ($obj);
					}
				}
			}
			
			return $links->toArray ();
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

			$data = $content['data'];

			if (empty($data)) {
				throw new Exception(
					'Payload either contains misformed JSON or missing "data" parameter.',
					static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS, BaseResponse::HTTP_BAD_REQUEST);
			}
			if (array_key_exists ("type", $data) === false) {
				throw new Exception(
					'"type" parameter not set in request.', static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
					BaseResponse::HTTP_BAD_REQUEST);
			}
			if ($data['type'] !== $type = Pluralizer::plural (s ($this->resourceName)->dasherize ()->__toString ())) {
				throw new Exception(
					'"type" parameter is not valid. Expecting ' . $type,
					static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS, BaseResponse::HTTP_CONFLICT);
			}
			if ($newRecord === false && !isset($data['id'])) {
				throw new Exception(
					'"id" parameter not set in request.', static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
					BaseResponse::HTTP_BAD_REQUEST);
			}
			
			unset ($content ['type']);
			
			return $data;
		}
		
		/**
		 * @param Model $model
		 * @param               $originalAttributes
		 *
		 * @return Model
		 *
		 */
		public function verifyIfModelChanged (Model $model, $originalAttributes) {
			// fetch the current attributes (post save)
			$newAttributes = $model->getAttributes ();
			
			// loop through the new attributes, and ensure they are identical
			// to the original ones. if not, then we need to return the model
			foreach ($newAttributes as $attribute => $value) {
				if (!array_key_exists ($attribute, $originalAttributes) ||
					$value !== $originalAttributes[$attribute]
				) {
					$model->markChanged ();
					break;
				}
			}
		}

		/**
		 * @return string
		 */
		private function dasherizedResourceName () {
			return s ($this->resourceName)->dasherize ()->__toString ();
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
			$include = $this->request->originalRequest->input('include');
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
			return array_diff($this->request->include, static::$exposedRelations);
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
					return BaseResponse::HTTP_CREATED;
				case 'PATCH':
				case 'PUT':
				case 'DELETE':
					return BaseResponse::HTTP_NO_CONTENT;
				case 'GET':
					return BaseResponse::HTTP_OK;
			}

			// Code shouldn't reach this point, but if it does we assume that the
			// client has made a bad request.
			return BaseResponse::HTTP_BAD_REQUEST;
		}
		
		/**
		 * Returns the models from a relationship. Will always return as array.
		 *
		 * @param  Model $model
		 * @param  string $relationKey
		 * @return array|\Illuminate\Database\Eloquent\Collection
		 * @throws Exception
		 */
		protected static function getModelsForRelation(Model $model, $relationKey) {
			if (!method_exists($model, $relationKey)) {
				throw new Exception(
					'Relation "' . $relationKey . '" does not exist in model',
					static::ERROR_SCOPE | static::ERROR_UNKNOWN_ID,
					BaseResponse::HTTP_INTERNAL_SERVER_ERROR
				);
			}
			$relationModels = $model->{$relationKey};
			if (is_null($relationModels)) {
				return null;
			}

			if (! $relationModels instanceof Collection) {
				return [ $relationModels ];
			}

			return $relationModels;
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
		 * @param  \EchoIt\JsonApi\Request $request
		 * @param  \EchoIt\JsonApi\Model $model
		 * @param integer $total the total number of records
		 * @return \Illuminate\Pagination\LengthAwarePaginator
		 */
		protected function handlePaginationRequest(Request $request, Model $model, $total = null) {
			$page = $request->pageNumber;
			$perPage = $request->pageSize;
			if (!$total) {
				$total = $model->count();
			}
			$results = $model->forPage($page, $perPage)->get(array('*'));
			$paginator = new LengthAwarePaginator($results, $total, $perPage, $page, [
				'path' => Paginator::resolveCurrentPath(),
				'pageName' => 'page[number]'
			]);
			$paginator->appends('page[size]', $perPage);
			if (!empty($request->filter)) {
				foreach ($request->filter as $key=>$value) {
					$paginator->appends($key, $value);
				}
			}
			if (!empty($request->sort)) {
				$paginator->appends('sort', implode(',', $request->sort));
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
			$shortClassName = Utils::getHandlerShortClassName();
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
	}
