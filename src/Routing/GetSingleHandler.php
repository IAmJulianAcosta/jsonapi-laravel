<?php
	/**
	 * Class GetSingleHandler
	 *
	 * @package IAmJulianAcosta\JsonApi\Routing
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Routing;
	
	use IAmJulianAcosta\JsonApi\Cache\CacheManager;
	use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
	use IAmJulianAcosta\JsonApi\Utils\StringUtils;
	
	class GetSingleHandler extends GetHandler {
		public function handle ($id = null) {
			$this->controller->beforeHandleGet();
			$this->controller->setRequestType(Controller::GET);
			
			forward_static_call_array ([$this->fullModelName, 'validateUserGetSinglePermissions'], [$this->request, \Auth::user(), $id]);
			
			$key             = CacheManager::getQueryCacheForSingleResource($id, StringUtils::dasherizedResourceName($this->resourceName));
			
			$controllerClass = get_class($this->controller);
			$model           = \Cache::remember(
				$key,
				$controllerClass::$cacheTime,
				function () {
					$query = $this->generateSelectQuery ();
					
					$query->where('id', $this->request->getId());
					/** @var Model $model */
					$model = $query->first ();
					
					$this->controller->afterModelQueried ($model);
					
					return $model;
				}
			);
			$this->controller->afterHandleGet ($model);
			
			return $model;
		}
	}
