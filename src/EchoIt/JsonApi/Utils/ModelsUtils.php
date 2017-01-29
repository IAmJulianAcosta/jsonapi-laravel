<?php
	/**
	 * Class ModelsUtils
	 *
	 * @package EchoIt\JsonApi\Utils
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Utils;
	
	use EchoIt\JsonApi\Data\ResourceObject;
	use EchoIt\JsonApi\Database\Eloquent\Model;
	use EchoIt\JsonApi\Data\ErrorObject;
	use EchoIt\JsonApi\Exception;
	use EchoIt\JsonApi\Http\Request;
	use EchoIt\JsonApi\Http\Response;
	use Illuminate\Database\Eloquent\Relations\Relation;
	use Illuminate\Support\Collection;
	
	class ModelsUtils {
		/**
		 * Iterate through result set to fetch the requested resources to include.
		 *
		 * @param $models
		 *
		 * @return Collection
		 */
		public static function getIncludedModels($models, Request $request) {
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
				$exposedRelations = $model->getExposedRelations();
				
				foreach ($exposedRelations as $relationName) {
					$modelsForRelation = static::getModelsForRelation($model, $relationName);
					
					if (is_null($modelsForRelation) === true) {
						continue;
					}
					
					//Each one of the models relations
					/** @var Model $modelForRelation */
					foreach ($modelsForRelation as $modelForRelation) {
						if ($modelForRelation instanceof Model === false) {
							throw new \InvalidArgumentException(
								"Model " . get_class($modelForRelation) . " is not a JSON API model");
						}
						// Check whether the object is already included in the response on it's ID
						$items = $includedModels->where($modelForRelation->getPrimaryKey(),
							$modelForRelation->getKey());
						
						if (self::checkIfItemIsDuplicated($items, $modelForRelation)) {
							continue;
						}
						
						$attributes = $modelForRelation->getAttributes();
						
						$modelForRelation->filterForeignKeys();
						self::filterUnwantedKeys($request, $relationName, $modelForRelation);
						
						$modelForRelation->setRawAttributes($attributes);
						
						$includedModels->push(new ResourceObject($modelForRelation));
					}
				}
			}
			
			return $includedModels;
		}
		
		/**
		 * Removed unwanted keys from attributes. Uses data from request to do it. This should be done in Relation, but
		 * that will require extending actual relations
		 *
		 * @param Request $request
		 * @param         $relationName
		 * @param Model   $model
		 *
		 * @see Relation::getEager()
		 */
		protected static function filterUnwantedKeys (Request $request, $relationName, Model $model) {
			$fields = $request->getFields()->get($relationName);
			if (empty($fields) === false) {
				$model->addVisible($fields);
			}
		}
		
		/**
		 * Returns the models from a relationship.
		 *
		 * @param  Model $model
		 * @param  string $relationKey
		 *
		 * @return Collection
		 * @throws Exception
		 */
		public static function getModelsForRelation(Model $model, $relationKey) {
			if (method_exists($model, $relationKey) === false) {
				Exception::throwSingleException (
					'Relation "' . $relationKey . '" does not exist in model',
					ErrorObject::RELATION_DOESNT_EXISTS_IN_MODEL, Response::HTTP_UNPROCESSABLE_ENTITY
				);
			}
			$relationModels = $model->{$relationKey};
			if (is_null($relationModels)) {
				return null;
			}
			
			if ( $relationModels instanceof Collection === false) {
				return new Collection([$relationModels]);
			}
			
			return $relationModels;
		}
		
		/**
		 * @param $items
		 * @param $modelForRelation
		 *
		 * @return bool
		 */
		protected static function checkIfItemIsDuplicated(Collection $items, Model $modelForRelation) {
			foreach ($items as $item) {
				/** @var $item Model */
				if ($item->getResourceType() === $modelForRelation->getResourceType()) {
					return true;
				}
			}
			return false;
		}
	}
