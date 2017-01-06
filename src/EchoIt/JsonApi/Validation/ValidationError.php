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
		
		//Validation errors
		const ACCEPTED = 0;
		const ACTIVE_URL = 1;
		const CONFIRMED = 2;
		const DIFFERENT = 3;
		const FILLED = 4;
		const IN = 5;
		const NULLABLE = 6;
		const NOT_IN = 7;
		const PRESENT = 8;
		const SAME = 9;
		const SIZE = 10;
		
		//Required errors
		const REQUIRED = 20;
		const REQUIRED_IF = 21;
		const REQUIRED_UNLESS = 22;
		const REQUIRED_WITH = 23;
		const REQUIRED_WITH_ALL = 24;
		const REQUIRED_WITHOUT = 25;
		const REQUIRED_WITHOUT_ALL = 26;
		
		//File type errors
		const MIMETYPES = 30;
		const MIMES = 31;
		
		//Validation date errors
		const AFTER = 40;
		const BEFORE = 41;
		const DATE_FORMAT = 42;
		
		//Database errors
		const EXISTS = 50;
		const UNIQUE = 51;
		
		//Text Format errors
		const ALPHA = 60;
		const ALPHA_DASH = 61;
		const ALPHA_NUMERIC = 62;
		const EMAIL = 63;
		const IP = 64;
		const REGEX = 65;
		const URL = 66;
		
		//Data type errors
		const ARRAY_ERROR = 70;
		const DATE = 71;
		const FILE = 72;
		const IMAGE = 73;
		const JSON = 74;
		const NUMERIC = 75;
		const STRING = 76;
		const TIMEZONE = 77;
		
		//Numeric type errors
		const BETWEEN = 80;
		const DIGITS = 81;
		const DIGITS_BETWEEN = 82;
		const INTEGER = 83;
		const MAX = 84;
		const MIN = 85;
		
		//Image type errors
		const DIMENSIONS = 90;
		
		//Array type errors
		const DISTINCT = 95;
		const IN_ARRAY = 96;
		
		const ERROR_LEVEL = 1;
		
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
			parent::__construct($message, $this->generateValidationErrorCode(), $this->generateHttpErrorCode());
		}
		
		protected function generateHttpErrorCode () {
			switch ($this->rule) {
				case "unique":
					return Response::HTTP_CONFLICT;
				default:
					return Response::HTTP_BAD_REQUEST;
			}
		}
		
		public function generateErrorCodeInThisLevel () {
			return $this->generateValidationErrorCode() * 100^static::ERROR_LEVEL;
		}
		
		protected function generateValidationErrorCode () {
			$rule = $this->rule;
			switch ($rule) {
				case "unique":
					//Returning before break, omitting.
					if ($this->attribute === 'email') {
						return static::EXISTING_USER_EMAIL;
					}
					else {
						return static::UNIQUE;
					}
				default:
					$constantRuleName = s($this->rule)->underscored()->toUpperCase()->__toString();
					try {
						return constant("ValidationError::$constantRuleName");
					}
					catch (\ErrorException $e) {
						return static::NULL_ERROR_CODE;
					}
			}
		}
		
		protected function normalizeErrorCode (&$rule) {
			if ($rule === "array") {
				$rule = "array_error";
			}
			else if ($rule === "alpha_num") {
				$rule = "alpha_numeric";
			}
		}
	}
