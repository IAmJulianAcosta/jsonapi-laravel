<?php

namespace EchoIt\JsonApi;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Pluralizer;
use Illuminate\Database\Eloquent\Relations\Pivot;
use \Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use EchoIt\JsonApi\CacheManager;
use Carbon\Carbon;
use Cache;
use Illuminate\Http\Response as BaseResponse;

use function Stringy\create as s;

abstract class Model extends \Eloquent {

	static protected $allowsModifyingByAllUsers;

	/**
	 * Validation rules
	 *
	 * @var array
	 */
	protected $rules = array();
	
	/**
	 * @var integer Amount time that response should be cached
	 */
	static protected $cacheTime = 60;
	
	/**
	 * Return the rules used when the model is updating
	 */
	protected abstract function getRulesOnUpdate ();

	/**
	 * Validates user input with the rules defined in the "$rules" static property
	 *
	 * @param array $rules
	 *
	 * @return bool
	 */
	public function validate ($rules = array()) {
		if (empty ($rules)) {
			$rules = $this->rules;
		}
		$validator = Validator::make ($this->attributes, $rules, $this->getValidationMessages ());

		if ($validator->passes ()) {
			return true;
		}

		$this->validationErrors = $validator->messages ();

		return false;
	}
	
	/**
	 * @return bool
	 * Validates user input when updating model
	 */
	public function validateOnUpdate () {
		return $this->validate ($this->getRulesOnUpdate ());
	}

	/**
	 * Validation error messages
	 *
	 * @var object
	 */
	protected $validationErrors;

	/**
	 * Returns validation errors if any
	 *
	 * @return object
	 */
	public function getValidationErrors () {
		return $this->validationErrors;
	}

	/**
	 * Validation messages
	 *
	 * @var array
	 */
	protected $validationMessages = array();

	/**
	 * Function that returns validation messages of this Class and parent Class merged
	 *
	 * @return array
	 */
	public function getValidationMessages () {
		return $this->validationMessages;
	}

	/**
	 * Friendly name of the model
	 *
	 * @var string
	 */
	public static $showName = "";

	/**
	 * Friendly name of the model (plural)
	 *
	 * @var string
	 */
	public static $showNamePlural = "";

	/**
	 * Genre: Male = true
	 *
	 * @var bool
	 */
	public static $genre = true;

	/**
	 *
	 * @return mixed
	 */
	public function getResourceType () {
		// return the resource type if it is not null; class name otherwise
		if ($this->resourceType) {
			return $this->resourceType;
		} else {
			$reflectionClass = new \ReflectionClass($this);

			return s ($reflectionClass->getShortName ())->dasherize ()->__toString ();
		}
	}

	/**
	 * Convert this model to an array, caching it before return
	 *
	 * @return array
	 */
	public function toArray () {
		if ($this->isChanged ()) {
			return $this->convertToJsonApiArray ();
		} else {
			if (empty($this->getRelations ())) {
				$key = CacheManager::getArrayCacheKeyForSingleResourceWithoutRelations($this->getResourceType(), $this->getKey());
			} else {
				$key = CacheManager::getArrayCacheKeyForSingleResource($this->getResourceType(), $this->getKey());
			}
			return Cache::remember (
				$key, static::$cacheTime,
				function () {
					return $this->convertToJsonApiArray ();
				}
			);
		}
	}

	/**
	 * Convert this model to an array with the JSON Api structure
	 *
	 * @return array
	 */
	private function convertToJsonApiArray () {

		//add type parameter
		$model_attributes = $this->attributesToArray ();
		$dasherized_model_attributes = array();

		foreach ($model_attributes as $key => $attribute) {
			$dasherized_model_attributes [$this->dasherizeKey($key)] = $attribute;
		}

		unset($dasherized_model_attributes[$this->primaryKey]);

		$attributes = [
			'id'         => $this->getKey (),
			'type'       => $this->getResourceType (),
			'attributes' => $dasherized_model_attributes,
			'links'      => array(
				'self' => $this->getModelURL ()
			)
		];
		
		$relations = $this->relationsToArray ();
		if (count ($relations) === 0) {
			return $attributes;
		}

		$relationships = ['relationships' => $relations];

		return array_merge ($attributes, $relationships);
	}

	/**
	 * Convert the relations of model to array
	 *
	 * @param $arrayableRelations
	 * @param $relations
	 * @return mixed
	 */
	private function relationsToArray () {
		$relations = [];
		
		// fetch the relations that can be represented as an array
		$arrayableRelations = $this->getArrayableRelations ();
		
		foreach ($arrayableRelations as $relation => $value) {
			//If relation is hidden, don't add
			if (in_array ($relation, $this->hidden)) {
				continue;
			}
			//If is Pivot, don't add
			else if ($value instanceof Pivot) {
				continue;
			}
			//If is Model
			else if ($value instanceof Model) {
				//Rename $value
				$model = $value;
				
				//Get resource type
				$resourceType = $model->getResourceType ();
				
				//Generate index of array to add
				$index = s($resourceType)->dasherize()->__toString();
				
				$relations[$index] = [
					'data' => $this->generateRelationArrayInformation($model, $resourceType)
				];
			}
			//If is Collection and has items
			elseif ($value instanceof Collection && $value->count () > 0) {
				//Rename value
				$collection = $value;
				
				//Get resource type from first item
				$resourceType = $collection->get (0)->getResourceType ();
				
				//Generate index of array to add
				$index = Pluralizer::plural (s ($resourceType)->dasherize ()->__toString ());
				
				//The relation to add is an array with a data key that is itself an array
				$relation = $relations[$index] = [];
				$relationData = $relation['data'] = [];
				
				//Iterate the collection and add to $relationData
				$collection->each (
					function (Model $model) use (&$relationData, $resourceType) {
						array_push ($relationData, $this->generateRelationArrayInformation($model, $resourceType));
					}
				);
			}

			// remove models / collections that we loaded from a method
			if (in_array ($relation, $this->relationsFromMethod)) {
				unset($this->$relation);
			}
			
			return $relations;
		}
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
			'id'   => s($model->getKey())->dasherize()->__toString(),
			'type' => Pluralizer::plural($resourceType)
		];
	}

	public function getModelURL () {
		return url (sprintf ('%s/%d', Pluralizer::plural($this->getResourceType ()), $this->id));
	}

	/**
	 * Create handler name from request name. Default output: Path\To\Model\ModelName
	 *
	 * @param string $modelName The name of the model
	 * @param bool $isPlural If is needed to convert this to singular
	 * @param bool $short Should return short name (without namespace)
	 * @param bool $toLowerCase Should return lowered case model name
	 * @param bool $capitalizeFirst
	 *
	 * @return string Class name of related resource
	 */
	public static function getModelClassName ($modelName, $namespace, $isPlural = true, $short = false, $toLowerCase = false,
	                                          $capitalizeFirst = true) {
		if ($isPlural) {
			$modelName = Pluralizer::singular ($modelName);
		}

		$className = "";
		if (!$short) {
			$className .= $namespace . '\\';
		}
		$className .= $toLowerCase ? strtolower ($modelName) : ucfirst ($modelName);
		$className = $capitalizeFirst ? s ($className)->upperCamelize ()->__toString () : s ($className)->camelize ()->__toString ();

		return $className;
	}
	
	public function getCreatedAtAttribute ($date) {
		return $this->getFormattedTimestamp ($date);
	}

	public function getUpdatedAtAttribute ($date) {
		return $this->getFormattedTimestamp ($date);
	}

	private function getFormattedTimestamp ($date) {
		if (is_null($date) === false) {
			return Carbon::createFromFormat("Y-m-d H:i:s", $date)->format('c');
		}
		return null;
	}
	
	public static function allowsModifyingByAllUsers () {
		return static::$allowsModifyingByAllUsers;
	}


	/**
	 * Let's guard these fields per default
	 *
	 * @var array
	 */
	protected $guarded = ['id', 'created_at', 'updated_at'];
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
	 * @var  null|string
	 */
	protected $resourceType = null;
	/**
	 * Expose the resource relations links by default when viewing a
	 * resource
	 *
	 * @var  array
	 */
	protected $defaultExposedRelations = [];
	protected $exposedRelations = [];
	
	/**
	 * An array of relation names of relations who
	 * simply return a collection, and not a Relation instance
	 *
	 * @var  array
	 */
	protected $relationsFromMethod = [];

	/**
	 * Get the model's exposed relations
	 *
	 * @return  Array
	 */
	public function exposedRelations () {
		if (empty($this->exposedRelations)) {
			return $this->defaultExposedRelations;
		}
		return $this->exposedRelations;
	}

	/**
	 * Get the model's relations that are from methods
	 *
	 * @return  Array
	 */
	public function relationsFromMethod () {
		return $this->relationsFromMethod;
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

	/**
	 * Validate passed values
	 *
	 * @param  Array $values user passed values (request data)
	 *
	 * @return bool|\Illuminate\Support\MessageBag  True on pass, MessageBag of errors on fail
	 */
	public function validateArray (Array $values) {
		if (count ($this->getValidationRules ())) {
			$validator = Validator::make ($values, $this->getValidationRules ());
			if ($validator->fails ()) {
				return $validator->errors ();
			}
		}
		return True;
	}

	/**
	 * Return model validation rules
	 * Models should overload this to provide their validation rules
	 *
	 * @return Array validation rules
	 */
	public function getValidationRules () {
		return [];
	}
	
	/**
	 * @param $key
	 *
	 * @return string
	 */
	protected function dasherizeKey ($key) {
		return s($key)->dasherize()->__toString();
	}
	
	/**
	 * @return string
	 */
	public function getPrimaryKey () {
		return $this->primaryKey;
	}
	
	/**
	 * Associate models' relationships
	 *
	 * @param array $data
	 * @param bool  $creating
	 *
	 * @throws Exception
	 */
	public function updateRelationships ($data, $modelsNamespace, $creating = false) {
		if (array_key_exists ("relationships", $data)) {
			//If we have a relationship object in the payload
			$relationships = $data ["relationships"];
			
			//Iterate all the relationships object
			foreach ($relationships as $relationshipName => $relationship) {
				if (is_array ($relationship)) {
					//If the relationship object is an array
					if (array_key_exists ('data', $relationship)) {
						//If the relationship has a data object
						$relationshipData = $relationship ['data'];
						if (is_array ($relationshipData)) {
							//One to one
							if (array_key_exists ('type', $relationshipData)) {
								$this->updateSingleRelationship ($relationshipData, $relationshipName, $creating, $modelsNamespace);
							}
							//One to many
							else if (count(array_filter(array_keys($relationshipData), 'is_string')) == 0) {
								$relationshipDataItems = $relationshipData;
								foreach ($relationshipDataItems as $relationshipDataItem) {
									if (array_key_exists ('type', $relationshipDataItem)) {
										$this->updateSingleRelationship ($relationshipDataItem, $relationshipName, $creating, $modelsNamespace);
									}
									else {
										throw new Exception(
											'Relationship type key not present in the request for an item',
											static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
											BaseResponse::HTTP_BAD_REQUEST);
									}
								}
							}
							else {
								throw new Exception(
									'Relationship type key not present in the request',
									static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
									BaseResponse::HTTP_BAD_REQUEST);
							}
						}
						else if (is_null ($relationshipData)) {
							//If the data object is null, do nothing, nothing to associate
						}
						else {
							//If the data object is not array or null (invalid)
							throw new Exception(
								'Relationship "data" object must be an array or null',
								static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS, BaseResponse::HTTP_BAD_REQUEST);
						}
					}
					else {
						throw new Exception(
							'Relationship must have an object with "data" key',
							static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS, BaseResponse::HTTP_BAD_REQUEST);
					}
				}
				else {
					//If the relationship is not an array, return error
					throw new Exception(
						'Relationship object is not an array', static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
						BaseResponse::HTTP_BAD_REQUEST);
				}
			}
		}
	}
	
	/**
	 * @param array  $relationshipData
	 * @param string $relationshipName
	 * @param bool   $creating
	 *
	 * @throws \EchoIt\JsonApi\Exception
	 */
	protected function updateSingleRelationship ($relationshipData, $relationshipName, $creating, $modelsNamespace) {
		//If we have a type of the relationship data
		$type                  = $relationshipData['type'];
		$relationshipModelName = Model::getModelClassName ($type, $modelsNamespace);
		$relationshipName      = s ($relationshipName)->camelize ()->__toString ();
		//If we have an id of the relationship data
		if (array_key_exists ('id', $relationshipData)) {
			/** @var $relationshipModelName Model */
			$relationshipId       = $relationshipData['id'];
			$newRelationshipModel = $relationshipModelName::find ($relationshipId);
			
			if ($newRelationshipModel) {
				//Relationship exists in model
				if (method_exists ($this, $relationshipName)) {
					/** @var Relation $relationship */
					$relationship = $this->$relationshipName ();
					//If creating, only update belongs to before saving. If not creating (updating), update
					if ($relationship instanceof BelongsTo && (($creating && $this->isDirty()) || !$creating)) {
						$relationship->associate ($newRelationshipModel);
					}
					//If creating, only update polymorphic saving. If not creating (updating), update
					else if ($relationship instanceof MorphOneOrMany && (($creating && !$this->isDirty()) || !$creating)) {
						$relationship->save ($newRelationshipModel);
						
					}
				}
				else {
					throw new Exception(
						"Relationship $relationshipName is not invalid",
						static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
						BaseResponse::HTTP_BAD_REQUEST);
				}
			}
			else {
				$formattedType = s(Pluralizer::singular($type))->underscored()->humanize()->toLowerCase()->__toString();
				throw new Exception(
					"Model $formattedType with id $relationshipId not found in database",
					static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
					BaseResponse::HTTP_BAD_REQUEST);
			}
		}
		else {
			throw new Exception(
				'Relationship id key not present in the request',
				static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
				BaseResponse::HTTP_BAD_REQUEST);
		}
	}
	
	/**
	 * Validates passed data against a model
	 * Validation performed safely and only if model provides rules
	 *
	 * @param  array                 $values passed array of values
	 *
	 * @throws Exception\Validation          Exception thrown when validation fails
	 *
	 * @return Bool                          true if validation successful
	 */
	public function validateData(array $values) {
		$validationResponse = $this->validateArray($values);
		
		if ($validationResponse === true) {
			return true;
		}
		
		throw new Exception\Validation(
			'Bad Request',
			static::ERROR_SCOPE | static::ERROR_HTTP_METHOD_NOT_ALLOWED,
			BaseResponse::HTTP_BAD_REQUEST,
			$validationResponse
		);
	}
	
	/**
	 * Load model relations
	 *
	 * @param array $requestedRelations
	 */
	public function loadRelatedModels($requestedRelations = []) {
		if (empty($requestedRelations)) {
			$this->exposedRelations = $this->defaultExposedRelations;
		}
		else {
			$this->exposedRelations = array_intersect($requestedRelations, $this->exposedRelations);
		}
		/** @var string $relation */
		foreach ($this->exposedRelations as $relation) {
			//Explode the relation separated by dots
			$relationArray = explode(".", $relation);
			
			//Pass the array to loadModels
			$this->loadModel($relationArray);
		}
	}
	
	/**
	 * @param array $relations
	 */
	private function loadModel ($relations) {
		//Get the first relation to load
		$relation = array_shift($relations);
		
		//Now load it
		
		// if this relation is loaded via a method, then call said method
		if (in_array($relation, $this->relationsFromMethod)) {
			$this->$relation = $this->$relation();
			return;
		}
		
		$this->load($relation);
		
		// If relations is not empty, load recursively
		if (empty($relations) === false) {
			/** @var Model $nestedModel */
			$nestedModel = $this->{$relationToLoad};
			$nestedModel->loadModel($relations);
		}
		
	}
	
	public static function queryAllModels ($columns = ['*']) {
		$columns = is_array($columns) ? $columns : func_get_args();
		
		$instance = new static;
		
		return $instance->newQuery();
	}
	
}
