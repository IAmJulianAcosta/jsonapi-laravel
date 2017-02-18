<?php
	/**
	 * Class DeleteHandler
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
	
	class DeleteHandler extends DataModifierHandler {
		public function handle($id = null) {
			$this->controller->beforeHandleDelete ();
			$this->controller->setRequestType (Controller::DELETE);
			
			$modelName = $this->fullModelName;
			
			$model = $this->tryToFindModel($modelName);
			
			$model->validateUserDeletePermissions ($this->request, Auth::user ());
			$model->validateOnDelete ($this->request);
			
			if (is_null ($model) === true) {
				return null;
			}
			
			$model->delete ();
			
			$this->controller->afterHandleDelete ($model);
			
			return $model;
		}
	}
