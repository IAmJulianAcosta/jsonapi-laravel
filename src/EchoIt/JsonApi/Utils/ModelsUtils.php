<?php
	/**
	 * Class ModelsUtils
	 *
	 * @package EchoIt\JsonApi\Utils
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Utils;
	
	use EchoIt\JsonApi\Database\Eloquent\Model;
	use EchoIt\JsonApi\Error;
	use EchoIt\JsonApi\Exception;
	use Illuminate\Http\Response;
	use Illuminate\Support\Collection;
	
	class ModelsUtils {
		
		/**
		 * Returns the models from a relationship. Will always return as array.
		 *
		 * @param  Model $model
		 * @param  string $relationKey
		 *
		 * @return array|\Illuminate\Database\Eloquent\Collection
		 * @throws Exception
		 */
		public static function getModelsForRelation(Model $model, $relationKey) {
			if (method_exists($model, $relationKey) === false) {
				throw new Exception([
						new Error ('Relation "' . $relationKey . '" does not exist in model',
							Error::RELATION_DOESNT_EXISTS_IN_MODEL, Response::HTTP_UNPROCESSABLE_ENTITY)
					]);
			}
			$relationModels = $model->{$relationKey};
			if (is_null($relationModels)) {
				return null;
			}
			
			if ( ! $relationModels instanceof Collection) {
				return [$relationModels];
			}
			
			return $relationModels;
		}
		
		/**
		 * Iterate through result set to fetch the requested resources to include.
		 *
		 * @param Model $models
		 *
		 * @return array
		 */
		public static function getIncludedModels($models) {
			$modelsCollection = new Collection();
			$models           = $models instanceof Collection ? $models : [$models];
			
			/** @var Model $model */
			foreach ($models as $model) {
				$exposedRelations = $model->exposedRelations();
				
				foreach ($exposedRelations as $relationName) {
					$value = static::getModelsForRelation($model, $relationName);
					
					if (is_null($value)) {
						continue;
					}
					
					//Each one of the models relations
					/* @var \EchoIt\JsonApi\Database\Eloquent\Model $obj */
					foreach ($value as $obj) {
						// Check whether the object is already included in the response on it's ID
						$duplicate = false;
						$items     = $modelsCollection->where($obj->getPrimaryKey(), $obj->getKey());
						
						if (count($items) > 0) {
							foreach ($items as $item) {
								/** @var $item Model */
								if ($item->getResourceType() === $obj->getResourceType()) {
									$duplicate = true;
									break;
								}
							}
							if ($duplicate) {
								continue;
							}
						}
						
						//add type property
						$attributes = $obj->getAttributes();
						
						$obj->setRawAttributes($attributes);
						
						$modelsCollection->push($obj);
					}
				}
			}
			
			return $modelsCollection->toArray();
		}
	}
