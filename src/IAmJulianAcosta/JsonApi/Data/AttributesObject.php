<?php
	/**
	 * Class AttributesObject
	 *
	 * @package IAmJulianAcosta\JsonApi\Data
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Data;
	
	use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\Response;
	use IAmJulianAcosta\JsonApi\Utils\StringUtils;
	
	class AttributesObject extends ResponseObject {
		/**
		 * @var Model $model
		 */
		protected $model;
		
		/**
		 * @var array
		 */
		protected $attributes;
		
		public function __construct(Model $model) {
			$this->setModel($model);
		}
		
		public function validateRequiredParameters() {
			if (isset ($this->attributes [$this->model->getPrimaryKey()])) {
				Exception::throwSingleException("Attributes must not have ID key",
					ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0);
			}
		}
		
		/**
		 * Convert this model to an array with the JSON Api structure
		 */
		protected function setParameters() {
			$model = $this->model;
			//add type parameter
			$model_attributes          = $model->attributesToArray();
			$dasherizedModelAttributes = [];
			
			foreach ($model_attributes as $key => $attribute) {
				$dasherizedModelAttributes [StringUtils::genreateMemberName($key)] = $attribute;
			}
			
			unset($dasherizedModelAttributes[$model->getPrimaryKey()]);
			
			$this->attributes = $dasherizedModelAttributes;
		}
		
		public function jsonSerialize () {
			return $this->attributes;
		}
		
		public function isEmpty() {
			return empty ($this->attributes);
		}
		
		public function setModel ($model) {
			$this->model = $model;
			$this->setParameters();
			$this->validateRequiredParameters();
		}
	}
