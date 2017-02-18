<?php
	/**
	 * Created by PhpStorm.
	 * User: julian-acosta
	 * Date: 2/18/17
	 * Time: 5:59 PM
	 */
	
	namespace IAmJulianAcosta\JsonApi\Routing;
	
	use IAmJulianAcosta\JsonApi\Cache\CacheManager;
	use IAmJulianAcosta\JsonApi\Data\LinkObject;
	use IAmJulianAcosta\JsonApi\Data\LinksObject;
	use IAmJulianAcosta\JsonApi\Data\RequestObject;
	use IAmJulianAcosta\JsonApi\Data\ResourceObject;
	use IAmJulianAcosta\JsonApi\Data\TopLevelObject;
	use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\Request;
	use IAmJulianAcosta\JsonApi\Http\Response;
	use IAmJulianAcosta\JsonApi\Utils\ModelsUtils;
	use IAmJulianAcosta\JsonApi\Utils\StringUtils;
	use Illuminate\Contracts\Pagination\LengthAwarePaginator;
	use Illuminate\Support\Collection;
	use Illuminate\Support\Facades\Cache;
	
	class ResponseGenerator {
		
		/**
		 * @var Controller
		 */
		protected $controller;
		
		
		/**
		 * @var Request
		 */
		protected $request;
		
		/**
		 * @var string
		 */
		protected $resourceName;
		
		public function __construct(Controller $controller) {
			$this->controller = $controller;
			$this->request = $controller->getRequest();
			$this->resourceName = $controller->getResourceName();
		}
		
		public function generateAdequateResponse ($model) {
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
			
			$controllerClass = get_class($this->controller);
			return Cache::remember (
				$key,
				$controllerClass::$cacheTime,
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
		 * @param $models
		 *
		 * @return ResourceObject|Collection
		 */
		protected function generateResourceObject ($models, Collection $modelsCollection) {
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
		
	}