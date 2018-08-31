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
   * @param Collection $models
   *
   * @param Request    $request
   *
   * @return Collection
   * @throws Exception
   * @throws \ReflectionException
   */
  public static function getIncludedModels(Collection $models, Request $request) {
    $includedModels = new Collection();

    /** @var Model $model */
    foreach ($models as $model) {
      $exposedRelations = $model->getExposedRelations();

      foreach ($exposedRelations as $relationName) {
        $modelsForRelation = static::getModelsForRelation($model, $relationName, $request->getFields());

        if (is_null($modelsForRelation)) {
          continue;
        }

        //Each one of the models relations
        /** @var Model $modelForRelation */
        foreach ($modelsForRelation as $modelForRelation) {
          //Check if object from collection is a model
          if (!$modelForRelation instanceof Model) {
            Model::throwInheritanceException(get_class($modelForRelation));
          }
          $includedModels->put(static::generateKey($modelForRelation), new ResourceObject($modelForRelation));
        }
      }
    }

    return $includedModels->values();
  }

  /**
   * Returns the models from a relationship.
   *
   * @param Model      $model
   * @param string     $relationKey
   * @param Collection $requestAllowedFields
   * @param Collection $models
   *
   * @return Collection
   * @throws Exception
   */
  public static function getModelsForRelation(Model $model, $relationKey, Collection $requestAllowedFields, Collection &$models = null
  ) {
    if (is_null($models)) {
      $models = new Collection();
    }

    //Convert relationKey to array, separated by "."
    $explodedRelationKeys = explode(".", $relationKey);

    $relationKey = array_shift($explodedRelationKeys);

    static::validateRelationMethodInModel($model, $relationKey);
    $relationModels = $model->{$relationKey};

    if (is_null($relationModels)) {
      return null;
    }
    else if ($relationModels instanceof Collection && $relationModels->isNotEmpty()) {
      /** @var Model $relationModel */

      $ids = $relationModels->map(function ($item) {
        return $item->id;
      })->toArray();
      $first = $relationModels->first();

      /** @var Model $modelClass */
      $modelClass = get_class($first);

      $primaryKey = $first->getPrimaryKey();
      $modelsFromDatabase = forward_static_call_array([$modelClass, 'with'], [$modelClass::$visibleRelations])
        ->whereIn($primaryKey, $ids)
        ->get();

      foreach ($modelsFromDatabase as $modelFromDatabase) {
        static::addModelToRelationModelsCollection($relationKey, $models, $requestAllowedFields, $modelFromDatabase);
      }
    }
    else if($relationModels instanceof Model) {
      /** @var Model $relationModel */
      $relationModel = $relationModels;

      self::filterUnwantedKeys($requestAllowedFields, $relationKey, $model);
      $modelClass = get_class($relationModel);
      $visibleRelations = $modelClass::$visibleRelations;
      $modelFromDatabase = $relationModels->fresh($visibleRelations);
      static::addModelToRelationModelsCollection($relationKey, $models, $requestAllowedFields, $modelFromDatabase);
    }

    return $models;
  }

  /**
   * @param Model $model
   * @param       $relationKey
   *
   * @throws Exception
   */
  protected static function validateRelationMethodInModel(Model $model, $relationKey) {
    if (!method_exists($model, $relationKey)) {
      Exception::throwSingleException('Relation "' . $relationKey . '" does not exist in model',
        ErrorObject::RELATION_DOESNT_EXISTS_IN_MODEL, Response::HTTP_UNPROCESSABLE_ENTITY);
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
  protected static function filterUnwantedKeys(Collection $fields, $relationName, Model $model) {
    $allowedFields = $fields->get($relationName);
    if (!empty($allowedFields)) {
      $model->addVisible($allowedFields);
    }
  }

  protected static function generateKey(Model $model) {
    return sprintf("%s_%s", get_class($model), $model->getKey());
  }

}
