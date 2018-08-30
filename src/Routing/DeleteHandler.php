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
  /**
   * @param null $id
   *
   * @return \IAmJulianAcosta\JsonApi\Database\Eloquent\Model|null
   * @throws \IAmJulianAcosta\JsonApi\Exception
   * @throws \Exception
   */
  public function handle($id = null) {
    $this->controller->beforeHandleDelete();
    $this->controller->setRequestType(Controller::DELETE);

    $modelName = $this->fullModelName;

    $model = $this->tryToFindModel($modelName);

    $model->validateUserDeletePermissions($this->request, Auth::user());
    $model->validateOnDelete($this->request);

    if (is_null($model)) {
      return null;
    }

    $model->delete();

    $this->controller->afterHandleDelete($model);

    return $model;
  }
}
