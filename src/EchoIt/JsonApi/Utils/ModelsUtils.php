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
		 * @param $models
		 *
		 * @return array
		 */
		public static function getIncludedModels($models) {
			$includedModels = new Collection();
			if ($models instanceof Collection === false) {
				if (is_array($models)) {
					$models = new Collection($models);
				}
				else {
					$models = new Collection([$models]);
				}
			}
			
			/** @var Model $model */
			foreach ($models as $model) {
				$exposedRelations = $model->exposedRelations();
				
				foreach ($exposedRelations as $relationName) {
					$modelsForRelation = static::getModelsForRelation($model, $relationName);
					
					if (is_null($modelsForRelation)) {
						continue;
					}
					
					//Each one of the models relations
					foreach ($modelsForRelation as $modelForRelation) {
						if ($modelForRelation instanceof Model === true) {
							// Check whether the object is already included in the response on it's ID
							$duplicate  = false;
							$key = $modelForRelation->getKey();
							$primaryKey = $modelForRelation->getPrimaryKey();
							$items      = $includedModels->where($primaryKey, $key);
							
							if (count($items) > 0) {
								foreach ($items as $item) {
									/** @var $item Model */
									if ($item->getResourceType() === $modelForRelation->getResourceType()) {
										$duplicate = true;
										break;
									}
								}
								if ($duplicate) {
									continue;
								}
							}
							
							//add type property
							$attributes = $modelForRelation->getAttributes();
							
							$modelForRelation->setRawAttributes($attributes);
							
							$includedModels->push($modelForRelation);
						}
						else {
							throw new \InvalidArgumentException("Model " . get_class($modelForRelation) . " is not a JSON API model");
						}
					}
				}
			}
			
			return $includedModels->toArray();
		}
	}
