<?php
/**
 * Class DeleteHandler
 *
 * @package IAmJulianAcosta\JsonApi\Routing
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Routing;

use Illuminate\Support\Facades\Auth;

class DeleteHandler extends DataModifierHandler {
  public function handle($id = null) {
    $this->controller->beforeHandleDelete();
    $this->controller->setRequestType(Controller::DELETE);

    $modelName = $this->fullModelName;

    $model = $this->tryToFindModel($modelName);

    $model->validateUserDeletePermissions($this->request, Auth::user());
    $model->validateOnDelete($this->request);

    if (is_null($model) === true) {
      return null;
    }

    $model->delete();

    $this->controller->afterHandleDelete($model);

    return $model;
  }
}
