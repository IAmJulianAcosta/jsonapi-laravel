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
	
	//Validation errors
	const ACCEPTED = 200;
	const ACTIVE_URL = 201;
	const CONFIRMED = 202;
	const DIFFERENT = 203;
	const FILLED = 204;
	const IN = 205;
	const NULLABLE = 206;
	const NOT_IN = 207;
	const PRESENT = 208;
	const SAME = 209;
	const SIZE = 210;
	
	//Required errors
	const REQUIRED = 220;
	const REQUIRED_IF = 221;
	const REQUIRED_UNLESS = 222;
	const REQUIRED_WITH = 223;
	const REQUIRED_WITH_ALL = 224;
	const REQUIRED_WITHOUT = 225;
	const REQUIRED_WITHOUT_ALL = 226;
	
	//File type errors
	const MIMETYPES = 230;
	const MIMES = 231;
	
	//Validation date errors
	const AFTER = 240;
	const BEFORE = 241;
	const DATE_FORMAT = 242;
	
	//Database errors
	const EXISTS = 250;
	const UNIQUE = 251;
	
	//Text Format errors
	const ALPHA = 260;
	const ALPHA_DASH = 261;
	const ALPHA_NUMERIC = 262;
	const EMAIL = 263;
	const IP = 264;
	const REGEX = 265;
	const URL = 266;
	
	//Data type errors
	const ARRAY_ERROR = 270;
	const DATE = 271;
	const FILE = 272;
	const IMAGE = 273;
	const JSON = 274;
	const NUMERIC = 275;
	const STRING = 276;
	const TIMEZONE = 277;
	
	//Numeric type errors
	const BETWEEN = 280;
	const DIGITS = 281;
	const DIGITS_BETWEEN = 282;
	const INTEGER = 283;
	const MAX = 284;
	const MIN = 285;
	
	//Image type errors
	const DIMENSIONS = 290;
	
	//Array type errors
	const DISTINCT = 295;
	const IN_ARRAY = 296;
	
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
			$rule = $this->rule;
			switch ($rule) {
				case "unique":
					if ($this->attribute === 'email') {
						return static::EXISTING_USER_EMAIL;
					}
					else {
						return static::DUPLICATED_RESOURCE;
					}
					break;
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
