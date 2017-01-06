<?php
	/**
	 * Class Error
	 *
	 * @package EchoIt\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi;
	
	class Error {
		const MULTIPLE_ERRORS                    = 0;
		const UNAUTHORIZED_ACCESS_TOKEN_PROVIDED = 1;
		const MALFORMED_ACCESS_TOKEN_PROVIDED    = 2;
		const MISSING_PARAMETER                  = 3;
		const INVALID_ACTION                     = 4;
		const MALFORMED_REQUEST                  = 5;
		
		const INVALID_USER_ID                    = 10;
		const USER_CANT_MODIFY_PROFILE           = 11;
		
		//Register
		const EMPTY_USER_LOGIN    = 20;
		const EXISTING_USER_LOGIN = 21;
		const EXISTING_USER_EMAIL = 22;
		
		//Login
		const INVALID_CREDENTIALS = 30;
		
		//Creating resources
		const DUPLICATED_RESOURCE = 40;
		
		//Security errors
		const LOCKOUT = 50;
		
		//Generating response errors
		const RELATION_DOESNT_EXISTS_IN_MODEL = 60;
		
		//Request Errors
		const UNKNOWN_LINKED_RESOURCES = 70;
		const NO_ID = 71;
		const INVALID_ATTRIBUTES = 72;
		const HTTP_METHOD_NOT_ALLOWED = 74;
		const VALIDATION_ERROR = 75;
		const UNAUTHORIZED = 76;
		
		//Other errors
		const NULL_ERROR_CODE      = 90;
		const UNKNOWN_ERROR        = 91;
		const SERVER_GENERIC_ERROR = 92;
		const DATABASE_ERROR       = 93;
		
		const ERROR_LEVEL = 0;
		
		/** @var integer */
		protected $httpErrorCode;
		
		/** @var string */
		protected $message;
		
		/** @var int */
		protected $errorCode;
		
		/** @var string */
		protected $title;
		
		/** @var array  */
		protected $additionalAttributes;
		
		/**
		 * @var int
		 */
		protected $resourceIdentifier;
		
		/**
		 * @return int
		 */
		public function getHttpErrorCode() {
			return $this->httpErrorCode;
		}
		
		/**
		 * @return int
		 */
		public function getErrorCode() {
			return $this->errorCode;
		}
		
		/**
		 * @return string
		 */
		public function getMessage() {
			return $this->message;
		}
		
		/**
		 * @return string
		 */
		public function getTitle () {
			return $this->title;
		}
		
		/**
		 * @param int $httpErrorCode
		 */
		public function setHttpErrorCode($httpErrorCode) {
			$this->httpErrorCode = $httpErrorCode;
		}
		
		/**
		 * @param string $message
		 */
		public function setMessage($message) {
			$this->message = $message;
		}
		
		/**
		 * @param int $errorCode
		 */
		public function setErrorCode($errorCode) {
			$this->errorCode = $errorCode;
		}
		
		/**
		 * @param string $title
		 */
		public function setTitle($title) {
			$this->title = $title;
		}
		
		/**
		 * @param array $additionalAttributes
		 */
		public function setAdditionalAttributes($additionalAttributes) {
			$this->additionalAttributes = $additionalAttributes;
		}
		
		/**
		 * @return array
		 */
		public function getAdditionalAttributes() {
			return $this->additionalAttributes;
		}
		
		public function __construct(
			$title,
			$errorCode,
			$httpErrorCode,
			$resourceIdentifier = 0,
			$message = null,
			$additionalAttributes = []
		) {
			$this->title                = $title;
			$this->errorCode            = $errorCode;
			$this->httpErrorCode        = $httpErrorCode;
			$this->resourceIdentifier   = $resourceIdentifier;
			$this->message              = $message;
			$this->additionalAttributes = $additionalAttributes;
		}
		
		/**
		 * Generates an error code combining a resource identifier and an error code.
		 *
		 * Example: 3001. That means a INVALID_CREDENTIALS error, of resource 1
		 *
		 * @return mixed
		 */
		public function generateErrorCode () {
			return $this->resourceIdentifier + $this->generateErrorCodeInThisLevel();
		}
		
		protected function generateErrorCodeInThisLevel () {
			return $this->errorCode * 100^static::ERROR_LEVEL;
		}
	}
