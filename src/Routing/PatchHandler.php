<?php
/**
 * Class PatchHandler
 *
 * @package IAmJulianAcosta\JsonApi\Routing
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Routing;

use IAmJulianAcosta\JsonApi\Cache\CacheManager;
use IAmJulianAcosta\JsonApi\Data\ErrorObject;
use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
use IAmJulianAcosta\JsonApi\Exception;
use IAmJulianAcosta\JsonApi\Http\Response;
use IAmJulianAcosta\JsonApi\Utils\StringUtils;
use IAmJulianAcosta\JsonApi\Validation\Validator;
use IAmJulianAcosta\JsonApi\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class PatchHandler extends DataModifierHandler {
  /**
   * @param null $id
   *
   * @return Model
   * @throws Exception
   * @throws \Throwable
   */
  public function handle($id = null) {
    $this->controller->beforeHandlePatch();
    $this->controller->setRequestType(Controller::PATCH);

    $modelName = $this->fullModelName;

    $model = $this->tryToFindModel($modelName);

    $model->validateUserUpdatePermissions($this->request, Auth::user());
    $model->validateOnUpdate($this->request);

    $originalAttributes = $model->getOriginal();

    $this->fillModelAttributes($model, $modelName);

    $model->updateRelationships($this->requestJsonApi->getRelationships(), $this->modelsNamespace, false);

    $this->controller->beforeSaveModel($model);
    $this->saveModel($model);
    $this->controller->afterSaveModel($model);

    $model->verifyIfModelChanged($originalAttributes);

    $this->clearCacheIfModelChanged($model);

    $this->controller->afterHandlePatch($model);

    return $model;
  }

  /**
   * @param Model $model
   * @param       $modelName
   *
   * @throws ValidationException
   */
  protected function fillModelAttributes(Model $model, $modelName) {
    $attributes = $this->requestJsonApi->getAttributes();
    if (!empty ($attributes)) {
      StringUtils::normalizeAttributes($attributes);

      /** @var Model $modelName */
      Validator::validateModelAttributes($attributes, $modelName::$validationRulesOnUpdate);
      $model->fill($attributes);
    }
  }

  /**
   * @throws Exception
   */
  protected function checkIfClientGeneratedIdWasSent() {
    if (!empty($this->requestJsonApi->getId())) {
      Exception::throwSingleException(
        "Creating a resource with a client generated ID is unsupported", ErrorObject::MALFORMED_REQUEST,
        Response::HTTP_FORBIDDEN
      );
    }
  }

  /**
   * @param Model $model
   *
   * @throws Exception
   */
  protected function checkIfEmptyModelWasCreated(Model $model) {
    if (empty($model)) {
      Exception::throwSingleException(
        'An unknown error occurred', ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }

  /**
   * @param Model $model
   *
   * @throws \ReflectionException
   */
  protected function clearCacheIfModelChanged(Model $model) {
    if ($model->isChanged()) {
      CacheManager::clearCache(StringUtils::dasherizedResourceName($this->resourceName), $this->requestJsonApi->getId(), $model);
    }
  }
}

