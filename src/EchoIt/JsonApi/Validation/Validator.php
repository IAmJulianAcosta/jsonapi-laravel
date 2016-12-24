<?php
	/**
	 * Class Validator
	 *
	 * @package EchoIt\JsonApi\Validation
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Validation;
	
	class Validator extends \Illuminate\Validation\Validator {
		protected $validationErrors = [];
		
		public function validationErrors () {
			return $this->validationErrors;
		}
		
		protected function addError($attribute, $rule, $parameters) {
			$message = $this->getMessage($attribute, $rule);
			
			$message = $this->doReplacements($message, $attribute, $rule, $parameters);
			
			$this->messages->add($attribute, $message);
			
			$this->validationErrors [] = new ValidationError($attribute, $rule, $message);
		}
	}
