<?php
	/**
	 * Class ModelsUtils
	 *
	 * @package IAmJulianAcosta\JsonApi\Utils
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Utils;
	
	use IAmJulianAcosta\JsonApi\Data\ResourceObject;
	use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
	use IAmJulianAcosta\JsonApi\Data\ErrorObject;
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\Request;
	use IAmJulianAcosta\JsonApi\Http\Response;
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
		public static function getIncludedModels(Collection $models, Request $request) {
			$includedModels = new Collection();
			
			/** @var Model $model */
			foreach ($models as $model) {
				$exposedRelations = $model->getExposedRelations();
				
				foreach ($exposedRelations as $relationName) {
					$modelsForRelation = static::getModelsForRelation($model, $relationName, $request->getFields());
					
					if (is_null($modelsForRelation) === true) {
						continue;
					}
					
					//Each one of the models relations
					/** @var Model $modelForRelation */
					foreach ($modelsForRelation as $modelForRelation) {
						//Check if object from collection is a model
						if ($modelForRelation instanceof Model === false) {
							throw new \InvalidArgumentException(
								"Model " . get_class($modelForRelation) . " is not a JSON API model"
							);
						}
						$includedModels->put(static::generateKey($modelForRelation), new ResourceObject($modelForRelation));
					}
				}
			}
			
			return $includedModels->values ();
		}
		
		/**
		 * Returns the models from a relationship.
		 *
		 * @param  Model     $model
		 * @param  string    $relationKey
		 *
		 * @param Collection $requestAllowedFields
		 * @param Collection $models
		 *
		 * @return Collection
		 */
		public static function getModelsForRelation (Model $model, $relationKey, Collection $requestAllowedFields, Collection &$models = null
		) {
			if (is_null($models) === true) {
				$models = new Collection();
			}
			
			//Convert relationKey to array, separated by "."
			$explodedRelationKeys = explode(".", $relationKey);
			
			$relationKey = array_shift($explodedRelationKeys);
			
			static::validateRelationMethodInModel($model, $relationKey);
			$relationModels = $model->{$relationKey};
			
			if (is_null($relationModels) === true) {
				return null;
			}
			else if ($relationModels instanceof Collection === true) {
				/** @var Model $relationModel */
				foreach ($relationModels as $relationModel) {
					static::processModelRelation($relationKey, $relationModel, $models, $explodedRelationKeys, $requestAllowedFields);
				}
			}
			else {
				/** @var Model $relationModel */
				$relationModel = $relationModels;
				static::processModelRelation($relationKey, $relationModel, $models, $explodedRelationKeys, $requestAllowedFields);
			}
			
			return $models;
		}
		
		/**
		 * @param            $relationKey
		 * @param Model      $relationModel
		 * @param Collection $models
		 * @param            $explodedRelationKeys
		 * @param Collection $requestAllowedFields
		 */
		protected static function processModelRelation($relationKey, Model $relationModel, Collection &$models,
			$explodedRelationKeys, Collection $requestAllowedFields
		) {
			static::addModelToRelationModelsCollection($relationKey, $models, $requestAllowedFields, $relationModel);
			static::loadRelationForModel($models, $relationModel, $explodedRelationKeys, $requestAllowedFields);
		}
		
		/**
		 * @param Model $model
		 * @param       $relationKey
		 */
		protected static function validateRelationMethodInModel(Model $model, $relationKey) {
			if (method_exists($model, $relationKey) === false) {
				Exception::throwSingleException('Relation "' . $relationKey . '" does not exist in model',
					ErrorObject::RELATION_DOESNT_EXISTS_IN_MODEL, Response::HTTP_UNPROCESSABLE_ENTITY);
			}
		}
		
		/**
		 * @param Collection $models
		 * @param            $relationModel
		 * @param            $explodedRelationKeys
		 */
		protected static function loadRelationForModel (Collection &$models, $relationModel, $explodedRelationKeys, $requestAllowedFields) {
			if (empty($explodedRelationKeys) === false) {
				static::getModelsForRelation($relationModel, implode(".", $explodedRelationKeys), $requestAllowedFields, $models);
			}
		}
		
		/**
		 * @param string     $relationKey
		 * @param Collection $models
		 * @param Collection $requestAllowedFields
		 * @param Model      $relationModel
		 */
		protected static function addModelToRelationModelsCollection(
			$relationKey, Collection &$models, Collection $requestAllowedFields, Model $relationModel
		) {
			$key = static::generateKey($relationModel);
			if ($models->get($key) === null) {
				//Remove foreign keys from model
				$relationModel->filterForeignKeys();
				
				//Remove unwanted keys
				self::filterUnwantedKeys($requestAllowedFields, $relationKey, $relationModel);
				
				//Add to collection
				$models->put($key, $relationModel);
			}
		}
	
		/**
		 * Removed unwanted keys from attributes. Uses data from request to do it. This should be done in Relation, but
		 * that will require extending actual relations
		 *
		 * @param Collection $fields
		 * @param            $relationName
		 * @param Model      $model
		 *
		 * @see Relation::getEager()
		 */
		protected static function filterUnwantedKeys (Collection $fields, $relationName, Model $model) {
			$allowedFields = $fields->get($relationName);
			if (empty($allowedFields) === false) {
				$model->addVisible($allowedFields);
			}
		}
		
		protected static function generateKey (Model $model) {
			return sprintf("%s_%s", get_class($model), $model->getKey());
		}
		

	}
