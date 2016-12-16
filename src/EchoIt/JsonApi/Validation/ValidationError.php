<?php
	/**
	 * Class ValidationError
	 *
	 * @package EchoIt\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Validation;
	use EchoIt\JsonApi\Error;
	use Illuminate\Http\Response;
	use function Stringy\create as s;
	
	class ValidationError extends Error {
		
		/** @var string */
		protected $attribute;
		
		/** @var string */
		protected $rule;
		
		/**
		 * @return string
		 */
		public function getRule() {
			return $this->rule;
		}
		
		/**
		 * @return string
		 */
		public function getAttribute() {
			return $this->attribute;
		}
		
		public function getTitle () {
			return sprintf("Error validating %s", $this->attribute);
		}
		
		public function __construct($attribute, $rule, $message) {
			$this->attribute     = s($attribute)->toLowerCase()->__toString();
			$this->rule          = s($rule)->toLowerCase()->__toString();
			parent::__construct($message, $this->generateErrorCode(), $this->generateHttpErrorCode());
		}
		
		protected function generateHttpErrorCode () {
			switch ($this->rule) {
				case "unique":
					return Response::HTTP_CONFLICT;
				default:
					return Response::HTTP_BAD_REQUEST;
			}
		}
		
		protected function generateErrorCode () {
			switch ($this->rule) {
				case "unique":
					if ($this->attribute === 'email') {
						return static::EXISTING_USER_EMAIL;
					}
					else {
						return static::DUPLICATED_RESOURCE;
					}
					break;
				default:
					return static::NULL_ERROR_CODE;
			}
		}
	}
