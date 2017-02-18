<?php

namespace IAmJulianAcosta\JsonApi\Database\Eloquent;

use IAmJulianAcosta\JsonApi\Data\ErrorObject;
use IAmJulianAcosta\JsonApi\Exception;
use IAmJulianAcosta\JsonApi\Http\Request;
use IAmJulianAcosta\JsonApi\Http\Response;
use IAmJulianAcosta\JsonApi\Utils\ClassUtils;
use IAmJulianAcosta\JsonApi\Utils\StringUtils;
use IAmJulianAcosta\JsonApi\Validation\ValidationException;
use IAmJulianAcosta\JsonApi\Validation\Validator;
use Illuminate\Support\Collection;
use Illuminate\Support\Pluralizer;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use function Stringy\create as s;

abstract class Model extends BaseModel {
	
	/**
	 * Validation rules when creating a new model
	 *
	 * @var array
	 */
	static protected $validationRulesOnCreate;
	
	/**
	 * Validation rules when updating a new model
	 *
	 * @var array
	 */
	static protected $validationRulesOnUpdate;
	
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
	 * Defines the exposed relations that are visible if no specific relations are requested.
	 *
	 * @var  array
	 */
	public static $defaultExposedRelations;
	
	/**
	 * If is relation is not present in this array, won't be returned, even if requested.
	 *
	 * @var  array
	 */
	public static $visibleRelations;
	
	/**
	 * Relations that will be returned by this model.
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
	public static function checkRequiredClassProperties () {
		foreach (static::$requiredClassProperties as $property) {
			$className = get_class(new static ());
			$propertyIsSet = isset ($className::$$property) === false;
			if ($propertyIsSet === true) {
				throw new \LogicException("Static property $property must be defined on $className model");
			}
		}
	}
	
	public static function validateAttributesOnCreate(array $attributes = []) {
		$validator = static::validateAttributes($attributes, false);
		if ($validator->fails() === true) {
			throw new ValidationException($validator->validationErrors());
		}
	}
	
	public static function validateAttributesOnUpdate(array $attributes = []) {
		$validator = static::validateAttributes($attributes, true);
		if ($validator->fails() === true) {
			throw new ValidationException($validator->validationErrors());
		}
	}
	
	/**
	 * @param array $attributes
	 * @param bool  $creatingModel
	 *
	 * @return Validator
	 */
	public static function validateAttributes (array $attributes, $creatingModel = false) {
		if ($creatingModel === true) {
			$validationRules = static::$validationRulesOnCreate;
		}
		else {
			$validationRules = static::$validationRulesOnUpdate;
		}
		
		return ValidatorFacade::make($attributes, $validationRules);
	}
	
	public function validate ($creatingModel = false) {
		$this->validator = static::validateAttributes($this->attributes, $creatingModel);
	}
	
	/**
	 * Validates if relationship object is valid.
	 *
	 * @param $relationship
	 */
	public function validateRelationship ($relationship) {
		if (is_array ($relationship) === true) {
			//If the relationship object is an array
			if (array_key_exists ('data', $relationship) === true) {
				//If the relationship has a data object
				$relationshipData = $relationship ['data'];
				if (is_array ($relationshipData) === true) {
					return true;
				}
				else if (is_null ($relationshipData) === false) {
					//If the data object is not array or null (invalid)
					Exception::throwSingleException(
						'Relationship "data" object must be an array', ErrorObject::INVALID_ATTRIBUTES,
						Response::HTTP_BAD_REQUEST
					);
				}
			}
			else {
				Exception::throwSingleException(
					'Relationship must have an object with "data" key', ErrorObject::INVALID_ATTRIBUTES,
					Response::HTTP_BAD_REQUEST
				);
			}
		}
		else {
			//If the relationship is not an array, return error
			Exception::throwSingleException(
				'Relationship object is not an array', ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST
			);
		}
	}
	
	/**
	 * Associate models' relationships
	 *
	 * @param bool  $creating
	 *
	 * @throws Exception
	 */
	public function updateRelationships ($relationships, $modelsNamespace, $creating = false) {
		if (empty($relationships) === false) {
			//Iterate all the relationships object
			foreach ($relationships as $relationshipName => $relationship) {
				if ($this->validateRelationship ($relationship)) {
					$relationshipData = $relationship ['data'];
					//One to one
					if (array_key_exists ('type', $relationshipData) === true) {
						$this->updateSingleRelationship ($relationshipData, $relationshipName, $creating, $modelsNamespace);
					}
					//One to many
					else if (count(array_filter(array_keys($relationshipData), 'is_string')) == 0) {
						$relationshipDataItems = $relationshipData;
						foreach ($relationshipDataItems as $relationshipDataItem) {
							if (array_key_exists ('type', $relationshipDataItem) === true) {
								$this->updateSingleRelationship ($relationshipDataItem, $relationshipName, $creating, $modelsNamespace);
							}
							else {
								Exception::throwSingleException(
									'Relationship type key not present in the request for an item',
									ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST
								);
							}
						}
					}
					else {
						Exception::throwSingleException(
							'Relationship type key not in the request', ErrorObject::INVALID_ATTRIBUTES,
							Response::HTTP_BAD_REQUEST
						);
					}
				}
			}
		}
	}
	
	/**
	 * @param array  $relationshipData
	 * @param string $relationshipName
	 * @param bool   $creating
	 *
	 * @throws \IAmJulianAcosta\JsonApi\Exception
	 */
	protected function updateSingleRelationship ($relationshipData, $relationshipName, $creating, $modelsNamespace) {
		//If we have a type of the relationship data
		$type                  = $relationshipData['type'];
		$relationshipModelName = ClassUtils::getModelClassName($type, $modelsNamespace);
		$relationshipName      = StringUtils::camelizeRelationshipName($relationshipName);
		
		$this->checkRelationshipId($relationshipData);
		
		$relationshipId = $relationshipData['id'];
		
		/** @var $relationshipModelName Model */
		
		//Relationship exists in model
		if (method_exists($this, $relationshipName) === true) {
			/** @var Relation $relationship */
			$relationship = $this->$relationshipName ();
			
			$newRelationshipModel = $this->getRelationshipModel($relationshipId);
			//If creating, only update belongs to before saving. If not creating (updating), update
			if ($this->shouldUpdateBelongsTo($creating, $relationship)) {
				/** @var BelongsTo $relationship */
				$relationship->associate($newRelationshipModel);
			} //If creating, only update polymorphic saving. If not creating (updating), update
			else if ($this->shouldUpdatePolymorphic($creating, $relationship)) {
				/** @var MorphOneOrMany $relationship */
				$relationship->save($newRelationshipModel);
			}
		}
		else {
			Exception::throwSingleException("Relationship $relationshipName is not valid",
				ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST);
		}
	}
	
	/**
	 * @param $creating
	 * @param $relationship
	 *
	 * @return bool
	 */
	protected function shouldUpdateBelongsTo($creating, $relationship) {
		$isBelongsto = $relationship instanceof BelongsTo;
		
		return $isBelongsto && (($creating === true && $this->exists === false) || $creating === false);
	}
	
	/**
	 * @param $creating
	 * @param $isMorphOneOrMany
	 *
	 * @return bool
	 */
	protected function shouldUpdatePolymorphic($creating, $isMorphOneOrMany) {
		$isMorphOneOrMany = $relationship instanceof MorphOneOrMany;
		return $isMorphOneOrMany && (($creating === true && $this->exists) || $creating === false);
	}
	
	protected function checkRelationshipId ($relationshipData) {
		//If we have an id of the relationship data
		if (array_key_exists ('id', $relationshipData) === false) {
			Exception::throwSingleException(
				'Relationship id key not present in the request', ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST
			);
		}
	}
	
	protected function getRelationshipModel ($modelId) {
		$newRelationshipModel = $relationshipModelName::find($relationshipId);
		
		if (empty($newRelationshipModel) === true) {
			$formattedType = s(Pluralizer::singular($type))->underscored()->humanize()->toLowerCase()->__toString();
			Exception::throwSingleException("Model $formattedType with id $relationshipId not found in database",
				ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST);
		}
		
		return $newRelationshipModel;
	}
	
	/*
	 * ========================================
	 *			     RELATIONS
	 * ========================================
	 */
	/**
	 * Load model relations
	 *
	 * @param array $requestedRelations
	 */
	public function loadRelatedModels($requestedRelations = []) {
		if (empty($requestedRelations) === true) {
			$this->exposedRelations = array_intersect(static::$visibleRelations, static::$defaultExposedRelations);
		}
		else {
			$this->exposedRelations = array_intersect(static::$visibleRelations, $requestedRelations);
		}
		/** @var string $relation */
		foreach ($this->exposedRelations as $relation) {
			//Explode the relation separated by dots
			$relationArray = explode(".", $relation);
			
			//Pass the array to loadModels
			$this->loadRelatedModel($relationArray);
		}
	}
	
	/**
	 * @param array $relations
	 */
	protected function loadRelatedModel ($relations) {
		//Get the first relation to load
		$relation = array_shift($relations);
		
		//Now load it
		if ($this->relationLoaded($relation) === false) {
			$this->load($relation);
		}
		
		// If relations is not empty, load recursively
		if (empty($relations) === false) {
			/** @var Model $nestedModel */
			$nestedModel = $this->{$relation};
			if ($nestedModel instanceof Model === true) {
				$nestedModel->loadRelatedModel($relations);
			}
			else if ($nestedModel instanceof Collection === true) {
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
	public function filterForeignKeys () {
		$attributes = $this->getArrayableAttributes();
		//We suppose that every attribute that ends in '_id' is a foreign key, but if convention is not
		//followed, an array with foreign keys can be used to store them
		foreach ($attributes as $attributeKey => $attribute) {
			if (ends_with($attributeKey, '_id') === true || ends_with($attributeKey, '-id') === true) {
				$this->addForeignKey($attributeKey);
			}
		}
	}
	
	/**
	 * @param $originalAttributes
	 */
	public function verifyIfModelChanged ($originalAttributes) {
		// fetch the current attributes (post save)
		$newAttributes = $this->getAttributes ();
		
		// loop through the new attributes, and ensure they are identical
		foreach ($newAttributes as $attribute => $value) {
			if (array_key_exists ($attribute, $originalAttributes) === false || $value !== $originalAttributes[$attribute]) {
				$this->markChanged ();
				break;
			}
		}
	}
	
	/**
	 * mark this model as changed
	 *
	 * @param   bool $changed
	 * @return  void
	 */
	public function markChanged ($changed = true) {
		$this->changed = (bool) $changed;
	}
	
	/**
	 * has this model been changed
	 *
	 * @return  bool
	 */
	public function isChanged () {
		return $this->changed;
	}
	
	public static function generateSelectQuery (array $relations = []) {
		$instance = new static;
		
		if (empty($relations) === true) {
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
	public function getCreatedAtAttribute ($date) {
		return $this->getFormattedTimestamp ($date);
	}
	
	public function getUpdatedAtAttribute ($date) {
		return $this->getFormattedTimestamp ($date);
	}
	
	/**
	 * @param $date
	 *
	 * @return null|string
	 */
	private function getFormattedTimestamp ($date) {
		if (is_null($date) === false) {
			return Carbon::createFromFormat("Y-m-d H:i:s", $date)->format('c');
		}
		return null;
	}
	
	/*
	 * ========================================
	 *		  PERMISSION VALIDATIONS
	 * ========================================
	 */
	public static function validateUserGetSinglePermissions (Request $request, $user, $id) {
		
	}
	
	public static function validateUserGetAllPermissions (Request $request, $user) {
		
	}
	
	public function validateUserCreatePermissions (Request $request, $user) {
		
	}
	
	public function validateUserUpdatePermissions (Request $request, $user) {
		
	}
	
	public function validateUserDeletePermissions (Request $request, $user) {
		
	}

    public function validateOnCreate (Request $request) {
		
    }
	
	public function validateOnUpdate (Request $request) {
		
	}
	
	public function validateOnDelete (Request $request) {
		
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
	
	public function addForeignKey ($foreignKey) {
		array_push($this->foreignKeys, $foreignKey);
	}
	
	/**
	 * Return model validation rules
	 * Models should overload this to provide their validation rules
	 *
	 * @return array validation rules
	 */
	public function getValidationRules () {
		return $this->validationRulesOnCreate;
	}
	
	/**
	 * @return string
	 */
	public function getPrimaryKey () {
		return $this->primaryKey;
	}
	
	/**
	 * Returns validation errors if any
	 *
	 * @return object
	 */
	public function getValidationErrors () {
		return $this->validationErrors;
	}
	
	/**
	 * Gets model exposed relations.
	 *
	 * @return array
	 */
	public function getExposedRelations () {
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
	 */
	public function getResourceType () {
		if (empty($this->resourceType) === false) {
			return $this->resourceType;
		}
		else {
			$reflectionClass = new \ReflectionClass($this);
			
			return $this->resourceType = Pluralizer::plural(
				StringUtils::dasherizedResourceName($reflectionClass->getShortName ())
			);
		}
	}
	
	/**
	 * @return string
	 */
	public function getModelURL () {
		if (empty ($this->modelURL) === true) {
			return $this->modelURL = url (sprintf ('%s/%d', Pluralizer::plural($this->getResourceType ()), $this->{$this->getPrimaryKey()}));
		}
		return $this->modelURL;
	}
	
	public static function throwInheritanceException ($modelName) {
		throw new \LogicException(
			sprintf ("Model %s is not a JSONAPI model", $modelName)
		);
	}
}
