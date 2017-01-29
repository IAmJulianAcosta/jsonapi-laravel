<?php
	/**
	 * Class RelationshipsObject
	 *
	 * @package EchoIt\JsonApi\Data
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Data;
	
	use EchoIt\JsonApi\Exception;
	use EchoIt\JsonApi\Http\Response;
	use \Illuminate\Database\Eloquent\Collection;
	use EchoIt\JsonApi\Database\Eloquent\Model;
	use Illuminate\Database\Eloquent\Relations\Pivot;
	use Illuminate\Support\Pluralizer;
	use function Stringy\create as s;
	
	class RelationshipsObject extends JSONAPIDataObject {
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
		
		public function __construct(Model $model = null, LinksObject $links = null, MetaObject $meta = null) {
			$this->model = $model;
			$this->links = $links;
			$this->meta = $meta;
			$this->validateRequiredParameters();
			$this->setParameters();
		}
		
		protected function validateRequiredParameters() {
			if (empty($this->links) === true) {
				if (empty ($this->model) === true && empty ($this->meta) === true) {
					Exception::throwSingleException(
						"Either 'model', 'links' or 'meta' object must be present on relationship object",
						ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0
					);
				}
			} else {
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
		 */
		private function setParameters() {
			$this->relationships = $this->relationsToArray();
		}
		
		public function relationsToArray() {
			$relations = [];
			
			// fetch the relations that can be represented as an array
			$arrayableRelations = $this->getModelArrayableItems ();
			
			foreach ($arrayableRelations as $relationName => $relationValue) {
				//If is Pivot, don't add
				if ($relationValue instanceof Pivot) {
					continue;
				}
				//If is Collection and has items
				elseif ($relationValue instanceof Collection && $relationValue->count () > 0) {
					//Rename relationValue
					/** @var Collection $collection */
					$collection = $relationValue;
					
					//Get resource type from first item
					$firstItem    = $collection->get(0);
					if ($firstItem instanceof Model) {
						$resourceType = $firstItem->getResourceType ();
						
						//Generate index of array to add
						$index = Pluralizer::plural (s ($resourceType)->dasherize ()->__toString ());
						
						//The relationName to add is an array with a data key that is itself an array
						$relationData = [];
						
						//Iterate the collection and add to $relationData
						$collection->each (
							function (Model $model) use (&$relationData, $resourceType) {
								$relationArrayInformation = $this->generateRelationArrayInformation($model, $resourceType);
								array_push ($relationData, $relationArrayInformation);
							}
						);
						$relationName = [
							'data' => $relationData
						];
						$relations[$index] = $relationName;
					}
					else {
						throw new \InvalidArgumentException("Model " . get_class($firstItem) . " is not a JSON API model");
					}
				}
				//If is Model
				else if ($relationValue instanceof Model) {
					//Rename $relationValue
					$model = $relationValue;
					
					//Get resource type
					$resourceType = $model->getResourceType ();
					
					//Generate index of array to add
					$index = s($resourceType)->dasherize()->__toString();
					
					$relations[$index] = [
						'data' => $this->generateRelationArrayInformation($model, $resourceType)
					];
				}
			}
			return $relations;
		}
		
		private function getModelArrayableItems () {
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
				'id'   => s($model->getKey())->dasherize()->__toString(),
				'type' => Pluralizer::plural($resourceType)
			];
		}
		
		public function jsonSerialize () {
			return $this->relationships;
		}
		
		public function isEmpty() {
			return empty ($this->relationships);
		}

	}
