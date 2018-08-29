<?php
	/**
	 * Class GetSingleHandler
	 *
	 * @package IAmJulianAcosta\JsonApi\Routing
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Routing;
	
	use IAmJulianAcosta\JsonApi\Cache\CacheManager;
	use IAmJulianAcosta\JsonApi\Data\ErrorObject;
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\Response;
	use IAmJulianAcosta\JsonApi\QueryFilter;
	use IAmJulianAcosta\JsonApi\SqlError;
	use IAmJulianAcosta\JsonApi\Utils\StringUtils;
	use Illuminate\Database\QueryException;
	use Illuminate\Support\Collection;
	
	class GetAllHandler extends GetHandler {
		public function handle ($id = null) {
			$this->controller->beforeHandleGetAll ();
			$this->controller->setRequestType(Controller::GET_ALL);
			
			forward_static_call_array ([$this->fullModelName, 'validateUserGetAllPermissions'], [$this->request, \Auth::user()]);
			
			$key = CacheManager::getQueryCacheForMultipleResources(StringUtils::dasherizedResourceName($this->resourceName));
			$controllerClass = get_class($this->controller);
			$models = \Cache::remember (
				$key,
				$controllerClass::$cacheTime,
				function () {
					$query = $this->generateSelectQuery ();
					
					QueryFilter::filterRequest($this->request, $query);
					QueryFilter::sortRequest($this->request, $query, $this->fullModelName);
					$this->controller->applyFilters ($query);
					
					try {
						//This method will execute get function inside paginate () or if not pagination present, inside itself.
						$model = QueryFilter::paginateRequest($this->request, $query);
					}
					catch (QueryException $exception) {
						throw new Exception(
							new Collection(
								[
									new SqlError('Database error', ErrorObject::DATABASE_ERROR,Response::HTTP_INTERNAL_SERVER_ERROR, $exception)
								]
							)
						);
					}
					
					$this->controller->afterModelsQueried ($model);
					
					return $model;
				}
			);
			$this->controller->afterHandleGetAll ($models);
			return $models;
		}
	}
