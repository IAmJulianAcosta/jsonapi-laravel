<?php
/**
 * Class PostHandler
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

class PostHandler extends DataModifierHandler {
  public function handle($id = null) {
    $this->controller->beforeHandlePost();
    $this->controller->setRequestType(Controller::POST);

    $this->checkIfClientGeneratedIdWasSent();

    $attributes = $this->requestJsonApi->getAttributes();
    StringUtils::normalizeAttributes($attributes);

    /** @var Model $modelName */
    $modelName = $this->fullModelName;
    Validator::validateModelAttributes($attributes, $modelName::$validationRulesOnCreate);

    /** @var Model $model */
    $model = new $modelName([$attributes]);
    $this->checkIfEmptyModelWasCreated($model);

    $model->validateUserCreatePermissions($this->request, \Auth::user());
    $model->validateOnCreate($this->request);

    //Update relationships twice, first to update belongsTo and then to update polymorphic and others
    $model->updateRelationships($this->requestJsonApi->getRelationships(), $this->modelsNamespace, true);

    $this->controller->beforeSaveNewModel($model);
    $this->saveModel($model);
    $this->controller->afterSaveNewModel($model);

    $model->updateRelationships($this->requestJsonApi->getRelationships(), $this->modelsNamespace, true);
    $model->markChanged();
    CacheManager::clearCache(StringUtils::dasherizedResourceName($this->resourceName));
    $this->controller->afterHandlePost($model);

    return $model;
  }

  protected function checkIfClientGeneratedIdWasSent() {
    if (!empty($this->requestJsonApi->getId())) {
      Exception::throwSingleException(
        "Creating a resource with a client generated ID is unsupported", ErrorObject::MALFORMED_REQUEST,
        Response::HTTP_FORBIDDEN
      );
    }
  }

  protected function checkIfEmptyModelWasCreated($model) {
    if (empty($model)) {
      Exception::throwSingleException(
        'An unknown error occurred', ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }
}
