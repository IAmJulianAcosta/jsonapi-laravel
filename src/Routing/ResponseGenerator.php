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

  /**
   * @param $model
   *
   * @return Response
   * @throws Exception
   * @throws \ReflectionException
   */
  public function generateAdequateResponse($model) {
    if ($this->request->getMethod() === 'GET') {
      $response = $this->generateCacheableResponse($model);
    }
    else {
      $response = $this->generateNonCacheableResponse($model);
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
  private function generateCacheableResponse($models) {
    $id = $this->request->getId();
    if (empty($id)) {
      $key = CacheManager::getResponseCacheForMultipleResources(StringUtils::dasherizedResourceName($this->resourceName));
    }
    else {
      $key = CacheManager::getResponseCacheForSingleResource($id,
        StringUtils::dasherizedResourceName($this->resourceName));
    }

    $controllerClass = get_class($this->controller);
    return Cache::remember(
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
   * @throws Exception
   * @throws \ReflectionException
   */
  private function generateNonCacheableResponse($models) {
    return $this->generateResponse($models, false);
  }

  /**
   * @param $models
   * @param $loadRelations
   *
   * @return Response
   * @throws Exception
   * @throws \ReflectionException
   */
  private function generateResponse($models, $loadRelations = true) {
    $links = $this->generateResponseLinks();

    /** @var Collection $modelsCollection */
    $modelsCollection = $this->getModelsAsCollection($models, $links);

    if ($loadRelations === true) {
      $this->loadRelatedModels($modelsCollection);
    }

    $included = ModelsUtils::getIncludedModels($modelsCollection, $this->request);
    $resourceObject = $this->generateResourceObject($models, $modelsCollection);
    $topLevelObject = new TopLevelObject($resourceObject, null, null, $included, $links);

    $response = new Response($topLevelObject,
      StatusCodeGenerator::successfulHttpStatusCode($this->request->getMethod(), $topLevelObject, $models));

    return $response;
  }

  /**
   * @return LinksObject
   * @throws Exception
   */
  protected function generateResponseLinks() {
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
   *
   * @throws Exception
   */
  private function loadRelatedModels(Collection $models) {
    /** @var Model $model */
    foreach ($models as $model) {
      $model->loadRelatedModels($this->request->getInclude());
    }
  }

  /**
   * @param Collection  $models
   * @param LinksObject $links
   *
   * @return Collection
   * @throws Exception
   */
  protected function getModelsAsCollection($models, LinksObject &$links) {
    if ($models instanceof LengthAwarePaginator) {
      /** @var LengthAwarePaginator $paginator */
      $paginator = $models;
      $modelsCollection = new Collection($paginator->items());
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
    return $modelsCollection;
  }

  /**
   * Return pagination links as array
   *
   * @param LengthAwarePaginator $paginator
   *
   * @return Collection
   * @throws Exception
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
   * @param            $models
   * @param Collection $modelsCollection
   *
   * @return ResourceObject|Collection
   * @throws Exception
   * @throws \ReflectionException
   */
  protected function generateResourceObject($models, Collection $modelsCollection) {
    //If we have only a model, this will be the top level object, if not, will be a collection of ResourceObject
    if ($models instanceof Model) {
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
}
