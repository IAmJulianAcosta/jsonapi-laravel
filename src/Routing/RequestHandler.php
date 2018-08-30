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
   * @param $modelsNamespace
   *
   * @return Model|Collection
   * @throws Exception
   */
  public function handleRequest($modelsNamespace) {
    $this->checkIfMethodIsSupported();
    $methodName = ClassUtils::methodHandlerName($this->request->getMethod());
    $model = $this->{$methodName}($modelsNamespace);

    $this->checkIfModelIsInvalid($model);

    return $model;
  }

  /**
   * Handle GET requests
   *
   * @param $modelsNamespace
   *
   * @return Model|Collection|null
   */
  protected function handleGet($modelsNamespace) {
    $id = $this->request->getId();

    if (empty($id)) {
      $handler = new GetAllHandler($this->controller, $modelsNamespace);
      return $handler->handle();
    }
    else {
      $handler = new GetSingleHandler($this->controller, $modelsNamespace);
      return $handler->handle($this->request->getId());
    }
  }

  /**
   * Handle POST requests
   *
   * @param $modelsNamespace
   *
   * @return Model
   * @throws Exception
   * @throws \IAmJulianAcosta\JsonApi\Validation\ValidationException
   * @throws \ReflectionException
   * @throws \Throwable
   */
  protected function handlePost($modelsNamespace) {
    $handler = new PostHandler($this->controller, $modelsNamespace);
    return $handler->handle($this->request->getId());
  }

  /**
   * Handle PATCH requests
   *
   * @param $modelsNamespace
   *
   * @return Model|null
   * @throws Exception
   * @throws \Throwable
   */
  protected function handlePatch($modelsNamespace) {
    $handler = new PatchHandler($this->controller, $modelsNamespace);
    return $handler->handle($this->request->getId());
  }

  /**
   * Handle PUT requests
   *
   * @param $modelsNamespace
   *
   * @return Model|null
   * @throws Exception
   * @throws \Throwable
   */
  protected function handlePut($modelsNamespace) {
    return $this->handlePatch($modelsNamespace);
  }

  /**
   * Handle DELETE requests
   *
   * @param $modelsNamespace
   *
   * @return Model|null
   * @throws Exception
   */
  protected function handleDelete($modelsNamespace) {
    $handler = new DeleteHandler($this->controller, $modelsNamespace);
    return $handler->handle($this->request->getId());
  }

  /**
   * Check whether a method is supported for a model. If not supported, throws and exception
   *
   * @throws Exception
   */
  protected function checkIfMethodIsSupported() {
    $method = $this->request->getMethod();
    $controllerClass = get_class($this->controller);
    if (!in_array(s($method)->toLowerCase(), $controllerClass::$supportedMethods)) {
      Exception::throwSingleException('Method not allowed', ErrorObject::HTTP_METHOD_NOT_ALLOWED,
        Response::HTTP_METHOD_NOT_ALLOWED);
    }
  }

  /**
   * @param $model
   *
   * @throws Exception
   */
  protected function checkIfModelIsInvalid($model) {
    if (is_null($model)) {
      Exception::throwSingleException(
        'Unknown ID', ErrorObject::UNKNOWN_ERROR, Response::HTTP_NOT_FOUND, static::ERROR_SCOPE
      );
    }
  }
}
