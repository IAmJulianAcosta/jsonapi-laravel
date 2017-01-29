<?php
	/**
	 * Class Validator
	 *
	 * @package EchoIt\JsonApi\Validation
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Validation;
	
	use Illuminate\Support\Collection;
	use Symfony\Component\Translation\TranslatorInterface;
	
	class Validator extends \Illuminate\Validation\Validator {
		protected $validationErrors;
		
		public function __construct(TranslatorInterface $translator, array $data, array $rules, array $messages,
			array $customAttributes = []
		) {
			parent::__construct($translator, $data, $rules, $messages, $customAttributes);
			$this->validationErrors = new Collection();
		}
		
		public function validationErrors () {
			return $this->validationErrors;
		}
		
		protected function addError($attribute, $rule, $parameters) {
			$message = $this->getMessage($attribute, $rule);
			
			$message = $this->doReplacements($message, $attribute, $rule, $parameters);
			
			$this->messages->add($attribute, $message);
			
			$this->validationErrors->push (new ValidationError($attribute, $rule, $message));
		}
	}
