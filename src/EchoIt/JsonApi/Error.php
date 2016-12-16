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
		
		//Weird errors
		const NULL_ERROR_CODE      = 100;
		const UNKNOWN_ERROR        = 101;
		const SERVER_GENERIC_ERROR = 102;
		
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
		 * @return array
		 */
		public function getAdditionalAttributes() {
			return $this->additionalAttributes;
		}
		
		//TODO use flags
		public function __construct($message, $errorCode, $httpErrorCode, $additionalAttributes = [], $title = "Error parsing JSON") {
			$this->message              = $message;
			$this->httpErrorCode        = $httpErrorCode;
			$this->errorCode            = $errorCode;
			$this->additionalAttributes = $additionalAttributes;
			$this->title                = $title;
		}
	}
