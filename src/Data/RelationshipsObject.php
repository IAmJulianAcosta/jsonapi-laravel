<?php
/**
 * Class RelationshipsObject
 *
 * @package IAmJulianAcosta\JsonApi\Data
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Data;

use IAmJulianAcosta\JsonApi\Exception;
use IAmJulianAcosta\JsonApi\Http\Response;
use \Illuminate\Database\Eloquent\Collection;
use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Pluralizer;
use IAmJulianAcosta\JsonApi\Utils\StringUtils;

class RelationshipsObject extends ResponseObject {
  /**
   * @var LinksObject
   */
  protected $links;

  /**
   * @var MetaObject
   */
  protected $meta;

  /**
   * @var Model $model
   */
  protected $model;

  /**
   * @var array
   */
  protected $relationships;

  /**
   * RelationshipsObject constructor.
   *
   * @param Model|null       $model
   * @param LinksObject|null $links
   * @param MetaObject|null  $meta
   *
   * @throws Exception
   * @throws \ReflectionException
   */
  public function __construct(Model $model = null, LinksObject $links = null, MetaObject $meta = null) {
    $this->model = $model;
    $this->links = $links;
    $this->meta = $meta;
    $this->validateRequiredParameters();
    $this->setParameters();
  }

  /**
   * @throws Exception
   */
  public function validateRequiredParameters() {
    if (empty($this->links)) {
      if (empty ($this->model) && empty ($this->meta)) {
        Exception::throwSingleException(
          "Either 'model', 'links' or 'meta' object must be present on relationship object",
          ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0
        );
      }
    }
    else {
      $links = $this->links->getLinks();
      if ($links->has('self') === false && $links->has('article') === false && $links->has('related') === false) {
        Exception::throwSingleException(
          "Links object of a relationship object must have an 'self', 'article' or 'related' link",
          ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0
        );
      }
    }
  }

  /**
   * Convert this model to an array with the JSON Api structure
   * @throws \ReflectionException
   */
  private function setParameters() {
    $this->relationships = $this->relationsToArray();
  }

  /**
   * @return array
   * @throws \ReflectionException
   */
  public function relationsToArray() {
    $relations = [];

    // fetch the relations that can be represented as an array
    $arrayableRelations = $this->getModelArrayableItems();

    foreach ($arrayableRelations as $relationName => $relationValue) {
      //If is Pivot, don't add
      if ($relationValue instanceof Pivot) {
        continue;
      } //If is Collection and has items
      else if ($relationValue instanceof Collection && $relationValue->count() > 0) {
        //Rename relationValue
        /** @var Collection $collection */
        $collection = $relationValue;

        //Get resource type from first item
        $firstItem = $collection->get(0);
        if ($firstItem instanceof Model) {
          $resourceType = $firstItem->getResourceType();

          //Generate index of array to add
          $index = StringUtils::dasherizedResourceName($relationName);

          //The relationName to add is an array with a data key that is itself an array
          $relationData = [];

          //Iterate the collection and add to $relationData
          $collection->each(
            function (Model $model) use (&$relationData, $resourceType) {
              $relationArrayInformation = $this->generateRelationArrayInformation($model, $resourceType);
              array_push($relationData, $relationArrayInformation);
            }
          );
          $relationName = [
            'data' => $relationData
          ];
          $relations[$index] = $relationName;
        }
        else {
          Model::throwInheritanceException(get_class($firstItem));
        }
      } //If is Model
      else if ($relationValue instanceof Model) {
        //Rename $relationValue
        $model = $relationValue;

        //Get resource type
        $resourceType = $model->getResourceType();

        //Generate index of array to add
        $index = StringUtils::dasherizedResourceName($relationName);

        $relations[$index] = [
          'data' => $this->generateRelationArrayInformation($model, $resourceType)
        ];
      }
    }
    return $relations;
  }

  private function getModelArrayableItems() {
    $model = $this->model;
    $values = $model->getRelations();
    if (count($model->getVisible()) > 0) {
      $values = array_intersect_key($values, array_flip($model->getVisible()));
    }

    if (count($model->getHidden()) > 0) {
      $values = array_diff_key($values, array_flip($model->getHidden()));
    }

    return $values;
  }

  /**
   * Generates the array required to generate an array representation of a model to use as included model
   *
   * @param Model $model
   * @param       $resourceType
   *
   * @return array
   */
  private function generateRelationArrayInformation(Model $model, $resourceType) {
    return [
      'id' => $model->getKey(),
      'type' => Pluralizer::plural($resourceType)
    ];
  }

  public function jsonSerialize() {
    return $this->relationships;
  }

  public function isEmpty() {
    return empty ($this->relationships);
  }

}
