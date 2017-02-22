<?php
	/**
	 * Class RelationUpdater
	 *
	 * @package IAmJulianAcosta\JsonApi\Database\Eloquent\Relations
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Database\Eloquent\Relations;
	
	use IAmJulianAcosta\JsonApi\Data\ErrorObject;
	use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\Response;
	use IAmJulianAcosta\JsonApi\Utils\ClassUtils;
	use IAmJulianAcosta\JsonApi\Utils\StringUtils;
	use Illuminate\Database\Eloquent\Relations\BelongsTo;
	use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
	use Illuminate\Database\Eloquent\Relations\Relation;
	use Illuminate\Support\Pluralizer;
	use function Stringy\create as s;
	
	
	class RelationUpdater {
		
		/**
		 * @var Model
		 */
		protected $model;
		
		public function __construct(Model $model) {
			$this->model = $model;
		}
		
		public function updateRelationships ($relationships, $modelsNamespace, $creating) {
			//Iterate all the relationships object
			foreach ($relationships as $relationshipName => $relationship) {
				if ($this->validateRelationship($relationship)) {
					$relationshipData = $relationship ['data'];
					
					//One to one
					if (array_key_exists('type', $relationshipData) === true) {
						$this->updateSingleRelationship($relationshipData, $relationshipName, $creating, $modelsNamespace);
					} //One to many
					else if (count(array_filter(array_keys($relationshipData), 'is_string')) == 0) {
						$relationshipDataItems = $relationshipData;
						$this->updateMultipleRelationships($modelsNamespace, $creating, $relationshipDataItems, $relationshipName);
					}
					else {
						Exception::throwSingleException('Relationship type key not in the request',
							ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST)
						;
					}
				}
			}
		}
		
		/**
		 * Validates if relationship object is valid.
		 *
		 * @param $relationship
		 *
		 * @return bool
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
		 * @param array  $relationshipData
		 * @param string $relationshipName
		 * @param bool   $creating
		 *
		 * @throws \IAmJulianAcosta\JsonApi\Exception
		 */
		public function updateSingleRelationship ($relationshipData, $relationshipName, $creating, $modelsNamespace) {
			//If we have a type of the relationship data
			$type                  = $relationshipData['type'];
			/** @var $relationshipModelName Model */
			$relationshipModelName = ClassUtils::getModelClassName($type, $modelsNamespace);
			$relationshipName      = StringUtils::camelizeRelationshipName($relationshipName);
			
			$this->checkRelationshipId($relationshipData);
			
			$relationshipId = $relationshipData['id'];
			
			//Relationship exists in model
			if (method_exists($this->model, $relationshipName) === true) {
				/** @var Relation $relationship */
				$relationship = $this->model->$relationshipName ();
				
				$newRelationshipModel = $this->getRelationshipModel($relationshipId, $relationshipModelName, $type);
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
		
		protected function checkRelationshipId ($relationshipData) {
			//If we have an id of the relationship data
			if (array_key_exists ('id', $relationshipData) === false) {
				Exception::throwSingleException(
					'Relationship id key not present in the request', ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST
				);
			}
		}
		
		/**
		 * @param $relationshipId
		 * @param $relationshipModelName
		 * @param $type
		 *
		 * @return Model
		 */
		protected function getRelationshipModel ($relationshipId, $relationshipModelName, $type) {
			$newRelationshipModel = forward_static_call_array ([$relationshipModelName, 'find'], [$relationshipId]);
			
			if (empty($newRelationshipModel) === true) {
				$formattedType = s(Pluralizer::singular($type))->underscored()->humanize()->toLowerCase()->__toString();
				Exception::throwSingleException("Model $formattedType with id $relationshipId not found in database",
					ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST);
			}
			
			return $newRelationshipModel;
		}
		
		/**
		 * @param $creating
		 * @param $relationship
		 *
		 * @return bool
		 */
		protected function shouldUpdateBelongsTo($creating, $relationship) {
			$isBelongsto = $relationship instanceof BelongsTo;
			
			return $isBelongsto && (($creating === true && $this->model->exists === false) || $creating === false);
		}
		
		/**
		 * @param $creating
		 * @param $relationship
		 *
		 * @return bool
		 */
		protected function shouldUpdatePolymorphic($creating, $relationship) {
			$isMorphOneOrMany = $relationship instanceof MorphOneOrMany;
			return $isMorphOneOrMany && (($creating === true && $this->model->exists) || $creating === false);
		}
		
		/**
		 * @param $modelsNamespace
		 * @param $creating
		 * @param $relationshipDataItems
		 * @param $relationshipName
		 */
		public function updateMultipleRelationships($modelsNamespace, $creating, $relationshipDataItems, $relationshipName) {
			foreach ($relationshipDataItems as $relationshipDataItem) {
				if (array_key_exists('type', $relationshipDataItem) === true) {
					$this->updateSingleRelationship($relationshipDataItem, $relationshipName, $creating, $modelsNamespace);
				} else {
					Exception::throwSingleException("Relationship type key not present in the request for $relationshipName",
						ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST);
				}
			}
		}
	}
