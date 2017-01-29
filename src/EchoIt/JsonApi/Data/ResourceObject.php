<?php
	/**
	 * Class ResourceObject
	 *
	 * @package EchoIt\JsonApi\Data
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Data;
	
	use Illuminate\Support\Collection;
	
	class ResourceObject extends ResourceIdentifierObject {
		/**
		 * @var array
		 */
		protected $attributes;
		
		/**
		 * @var LinksObject
		 */
		protected $links;
		
		/**
		 * @var array
		 */
		protected $relationships;
		
		/**
		 * Convert this model to an array with the JSON Api structure
		 */
		protected function setParameters() {
			parent::setParameters();
			$model = $this->model;
			
			$this->attributes    = new AttributesObject($model);
			$this->links         = new LinksObject(new Collection([new LinkObject('self', $model->getModelURL())]));
			$this->relationships = new RelationshipsObject($model);
		}
		
		public function jsonSerialize () {
			$returnArray = parent::jsonSerialize();
			
			$this->pushInstanceObjectToReturnArray($returnArray, "attributes");
			$this->pushInstanceObjectToReturnArray($returnArray, "relationships");
			$this->pushInstanceObjectToReturnArray($returnArray, "links");
			
			return $returnArray;
		}
		
		/**
		 * @return array
		 */
		public function getAttributes() {
			return $this->attributes;
		}
		
		/**
		 * @param array $attributes
		 */
		public function setAttributes($attributes) {
			$this->attributes = $attributes;
		}
		
		/**
		 * @return LinksObject
		 */
		public function getLinks() {
			return $this->links;
		}
		
		/**
		 * @param LinksObject $links
		 */
		public function setLinks($links) {
			$this->links = $links;
		}
		
		/**
		 * @return array
		 */
		public function getRelationships() {
			return $this->relationships;
		}
		
		/**
		 * @param array $relationships
		 */
		public function setRelationships($relationships) {
			$this->relationships = $relationships;
		}
	}
