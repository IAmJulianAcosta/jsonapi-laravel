<?php

namespace IAmJulianAcosta\JsonApi\Database\Eloquent;

use IAmJulianAcosta\JsonApi\Data\ErrorObject;
use IAmJulianAcosta\JsonApi\Database\Eloquent\Relations\RelationUpdater;
use IAmJulianAcosta\JsonApi\Exception;
use IAmJulianAcosta\JsonApi\Http\Response;
use IAmJulianAcosta\JsonApi\Validation\ValidationException;
use IAmJulianAcosta\JsonApi\Http\Request;
use IAmJulianAcosta\JsonApi\Utils\StringUtils;
use IAmJulianAcosta\JsonApi\Validation\Validator;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Pluralizer;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Carbon\Carbon;

abstract class Model extends BaseModel {

  /**
   * Validation rules when creating a new model
   *
   * @var array
   */
  static public $validationRulesOnCreate;

  /**
   * Validation rules when updating a new model
   *
   * @var array
   */
  static public $validationRulesOnUpdate;

  /**
   * @var integer Amount time that response should be cached
   */
  static protected $cacheTime = 0;

  /**
   * Represents the
   *
   * @var array
   */
  protected $foreignKeys = [];

  /**
   * Validation error messages
   *
   * @var Validator
   */
  protected $validator;

  /**
   * Let's guard these fields per default
   *
   * @var array
   */
  protected $guarded = ['id', 'created_at', 'updated_at'];

  /**
   * Array that contains columns that doesn't support sort operation on database
   *
   * @var array
   */
  protected static $nonSortableColumns;

  /**
   * Has this model been changed inother ways than those
   * specified by the request
   *
   * Ref: http://jsonapi.org/format/#crud-updating-responses-200
   *
   * @var  boolean
   */
  protected $changed = false;

  /**
   * The resource type. If null, when the model is rendered,
   * the table name will be used
   *
   * @var  string
   */
  protected $resourceType;

  /**
   * Defines the exposed relations that will be returned as included models if no specific relations are requested.
   *
   * @var  array
   */
  public static $defaultExposedRelations = [];

  /**
   * If is relation is not present in this array, won't be returned, even if requested.
   *
   * @var  array
   */
  public static $visibleRelations = [];

  /**
   * Relations that will be returned in the included object of this model. This array is generated in runtime
   *
   * @var array
   */
  protected $exposedRelations = [];

  /**
   * @var string
   */
  protected $modelURL;

  public static $requiredClassProperties = [
    'defaultExposedRelations',
    'validationRulesOnUpdate',
    'validationRulesOnCreate',
    'nonSortableColumns'
  ];

  public function __construct(array $attributes = []) {
    parent::__construct($attributes);
  }

  /**
   * This function check if all configuration variables are set for this model
   */
  public static function checkRequiredClassProperties() {
    foreach (static::$requiredClassProperties as $property) {
      $className = get_class(new static ());
      if (!isset ($className::$$property)) {
        throw new \LogicException("Static property $property must be defined on $className model");
      }
    }
  }

  /**
   * @param bool $creatingModel
   *
   * @throws ValidationException
   */
  public function validate($creatingModel = false) {
    $this->validator = Validator::validateModelAttributes(
      $this->attributes,
      $creatingModel ? static::$validationRulesOnCreate : static::$validationRulesOnUpdate
    );
  }

  /*
   * ========================================
   *		    RELATIONSHIPS UPDATING
   * ========================================
   */

  /**
   * Associate models' relationships
   *
   * @param      $relationships
   * @param      $modelsNamespace
   * @param bool $creating
   *
   */
  public function updateRelationships($relationships, $modelsNamespace, $creating = false) {
    $relationUpdater = new RelationUpdater($this);
    $relationUpdater->updateRelationships($relationships, $modelsNamespace, $creating);
  }

  /*
   * ========================================
   *			  RELATIONS LOADING
   * ========================================
   */
  /**
   * Load model relations
   *
   * @param array $requestedRelations
   *
   * @throws Exception
   */
  public function loadRelatedModels($requestedRelations = []) {
    if (empty($requestedRelations)) {
      $this->exposedRelations = array_intersect(static::$visibleRelations, static::$defaultExposedRelations);
    }
    else {
      $this->exposedRelations = array_intersect(static::$visibleRelations, $requestedRelations);
    }
    /** @var string $relation */
    foreach (static::$visibleRelations as $relation) {
      //Explode the relation separated by dots
      $relationArray = explode(".", $relation);

      //Pass the array to loadModels
      $this->loadRelatedModel($relationArray);
    }
  }

  /**
   * @param array $relations
   *
   * @throws Exception
   */
  protected function loadRelatedModel($relations) {
    //Get the first relation to load
    $relation = array_shift($relations);

    //Now load it
    try {
      if (!$this->relationLoaded($relation)) {
        $this->load($relation);
      }
    }
    catch(RelationNotFoundException $e) {
      Exception::throwSingleException('Invalid relationship: ' . $relation, ErrorObject::INVALID_RELATION,
        Response::HTTP_NOT_IMPLEMENTED, 0, 'The relationship wasn\'t declared in the model, so you need to declare it on order to make this work');
    }

    // If relations is not empty, load recursively
    if (!empty($relations)) {
      /** @var Model $nestedModel */
      $nestedModel = $this->{$relation};
      if ($nestedModel instanceof Model) {
        $nestedModel->loadRelatedModel($relations);
      }
      else if ($nestedModel instanceof Collection) {
        $nestedModels = $nestedModel;
        foreach ($nestedModels as $nestedModel) {
          $nestedModel->loadRelatedModel($relations);
        }
      }
    }
  }

  /**
   *
   */
  public function filterForeignKeys() {
    $attributes = $this->getArrayableAttributes();
    //We suppose that every attribute that ends in '_id' is a foreign key, but if convention is not
    //followed, an array with foreign keys can be used to store them
    foreach ($attributes as $attributeKey => $attribute) {
      if (ends_with($attributeKey, '_id') || ends_with($attributeKey, '-id')) {
        $this->addForeignKey($attributeKey);
      }
    }
  }

  /*
   * ========================================
   *		        MODEL CHANGED
   * ========================================
   */
  /**
   * @param $originalAttributes
   */
  public function verifyIfModelChanged($originalAttributes) {
    // fetch the current attributes (post save)
    $newAttributes = $this->getAttributes();

    // loop through the new attributes, and ensure they are identical
    foreach ($newAttributes as $attribute => $value) {
      if (!array_key_exists($attribute, $originalAttributes) || $value !== $originalAttributes[$attribute]) {
        $this->markChanged();
        break;
      }
    }
  }

  /**
   * mark this model as changed
   *
   * @param   bool $changed
   *
   * @return  void
   */
  public function markChanged($changed = true) {
    $this->changed = (bool)$changed;
  }

  /**
   * has this model been changed
   *
   * @return  bool
   */
  public function isChanged() {
    return $this->changed;
  }

  /*
   * ========================================
   *		         SELECT QUERY
   * ========================================
   */
  public static function generateSelectQuery(array $relations = []) {
    $instance = new static;

    if (empty($relations)) {
      return $instance->newQuery();
    }
    else {
      return static::with(array_merge($relations, static::$defaultExposedRelations));
    }
  }

  /*
   * ========================================
   *		  ACCESSORS AND MUTATORS
   * ========================================
   */
  public function getCreatedAtAttribute($date) {
    return $this->getFormattedTimestamp($date);
  }

  public function getUpdatedAtAttribute($date) {
    return $this->getFormattedTimestamp($date);
  }

  /**
   * @param $date
   *
   * @return null|string
   */
  protected function getFormattedTimestamp($date) {
    if (!is_null($date)) {
      return Carbon::createFromFormat("Y-m-d H:i:s", $date)->format('c');
    }
    return null;
  }

  /*
   * ========================================
   *		  PERMISSION VALIDATIONS
   * ========================================
   */
  public static function validateUserGetSinglePermissions(Request $request, $user, $id) {

  }

  public static function validateUserGetAllPermissions(Request $request, $user) {

  }

  public function validateUserCreatePermissions(Request $request, $user) {

  }

  public function validateUserUpdatePermissions(Request $request, $user) {

  }

  public function validateUserDeletePermissions(Request $request, $user) {

  }

  public function validateOnCreate(Request $request) {

  }

  public function validateOnUpdate(Request $request) {

  }

  public function validateOnDelete(Request $request) {

  }

  /*
   * ========================================
   *		   GETTERS AND SETTERS
   * ========================================
   */
  /**
   * @return array
   */
  public function getHidden() {
    return array_merge(parent::getHidden(), $this->getForeignKeys());
  }

  /**
   * @return array
   */
  public function getForeignKeys() {
    return $this->foreignKeys;
  }

  /**
   * @param array $foreignKeys
   */
  public function setForeignKeys($foreignKeys) {
    $this->foreignKeys = $foreignKeys;
  }

  public function addForeignKey($foreignKey) {
    array_push($this->foreignKeys, $foreignKey);
  }

  /**
   * Return model validation rules
   * Models should overload this to provide their validation rules
   *
   * @return array validation rules
   */
  public function getValidationRules() {
    return $this->validationRulesOnCreate;
  }

  /**
   * @return string
   */
  public function getPrimaryKey() {
    return $this->primaryKey;
  }

  /**
   * Returns validation errors if any
   *
   * @return object
   */
  public function getValidationErrors() {
    return $this->validationErrors;
  }

  /**
   * Gets model exposed relations.
   *
   * @return array
   */
  public function getExposedRelations() {
    return $this->exposedRelations;
  }

  /**
   * @param array $exposedRelations
   */
  public function setExposedRelations($exposedRelations) {
    $this->exposedRelations = array_intersect(static::$visibleRelations, $exposedRelations);
  }

  /**
   * Returns the resource type if it is not null; class name otherwise
   * @return string
   * @throws \ReflectionException
   */
  public function getResourceType() {
    if (!empty($this->resourceType)) {
      return $this->resourceType;
    }
    else {
      $reflectionClass = new \ReflectionClass($this);

      return $this->resourceType = Pluralizer::plural(
        StringUtils::dasherizedResourceName($reflectionClass->getShortName())
      );
    }
  }

  /**
   * @return string
   * @throws \ReflectionException
   */
  public function getModelURL() {
    if (empty ($this->modelURL)) {
      return $this->modelURL = url(sprintf('%s/%d', Pluralizer::plural($this->getResourceType()), $this->{$this->getPrimaryKey()}));
    }
    return $this->modelURL;
  }

  public static function throwInheritanceException($modelName) {
    throw new \InvalidArgumentException(
      sprintf("Model %s is not a JSONAPI model", $modelName)
    );
  }
}
