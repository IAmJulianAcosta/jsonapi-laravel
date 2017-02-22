<?php
	/**
	 * Class ErrorObject
	 *
	 * @package IAmJulianAcosta\JsonApi\Data
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Data;
	
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\Response;
	
	class ErrorObject extends ResponseObject {
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
		const LOGIC_ERROR          = 99;
		
		const ERROR_LEVEL = 0;
		
		/**
		 * The HTTP status code applicable to this problem.
		 * @var integer
		 */
		protected $status;
		
		/**
		 * A human-readable explanation specific to this occurrence of the problem.
		 * @var string
		 */
		protected $detail;
		
		/**
		 * An application-specific error code
		 * @var int
		 */
		protected $code;
		
		/**
		 * A short, human-readable summary of the problem that SHOULD NOT change from occurrence to occurrence of the problem
		 * @var string
		 */
		protected $title;
		
		/**
		 * A meta object containing non-standard meta-information about the error
		 * @var MetaObject
		 */
		protected $meta;
		
		/**
		 * @var int
		 */
		protected $resourceIdentifier;
		
		public function __construct($title, $code, $status, $resourceIdentifier = 0, $detail = null, MetaObject $meta = null
		) {
			$this->title              = $title;
			$this->code               = $code;
			$this->status             = $status;
			$this->resourceIdentifier = $resourceIdentifier;
			$this->detail             = $detail;
			$this->meta               = $meta;
		}
		
		public function validateRequiredParameters() {
			if (empty ($this->key) === true) {
				Exception::throwSingleException("Title must be present on errors object",
					ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0);
			}
			if (empty ($this->code) === true) {
				Exception::throwSingleException("Code must be present on errors object",
					ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0);
			}
			if (empty ($this->status) === true) {
				Exception::throwSingleException("Status must be present on errors object",
					ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0);
			}
		}
		
		/**
		 * Generates an error code combining a resource identifier and an error code.
		 *
		 * Example: 3001. That means a INVALID_CREDENTIALS error, of resource 1
		 *
		 * @return mixed
		 */
		public function generateErrorCode () {
			return $this->resourceIdentifier + $this->generateErrorCodeOnThisLevel();
		}
		
		protected function generateErrorCodeOnThisLevel () {
			return $this->code * 100 ^ static::ERROR_LEVEL;
		}
		
		/**
		 * @inheritdoc
		 */
		public function jsonSerialize () {
			$this->pushToReturnArray($returnArray, "id", (string) microtime());
			$this->pushToReturnArray($returnArray, "code", $this->generateErrorCode());
			$this->pushInstanceObjectToReturnArray($returnArray, "title");
			$this->pushInstanceObjectToReturnArray($returnArray, "detail");
			$this->pushInstanceObjectToReturnArray($returnArray, "status");
			$this->pushInstanceObjectToReturnArray($returnArray, "meta");
			
			return $returnArray;
		}
		
		public function isEmpty() {
			return empty ($this->title) && empty ($this->code) && empty ($this->status);
		}
		
		/**
		 * @return int
		 */
		public function getStatus() {
			return $this->status;
		}
		
		/**
		 * @return int
		 */
		public function getCode() {
			return $this->code;
		}
		
		/**
		 * @return string
		 */
		public function getDetail() {
			return $this->detail;
		}
		
		/**
		 * @return string
		 */
		public function getTitle () {
			return $this->title;
		}
		
		/**
		 * @param int $status
		 */
		public function setStatus($status) {
			$this->status = $status;
			$this->validateRequiredParameters();
		}
		
		/**
		 * @param string $detail
		 */
		public function setDetail($detail) {
			$this->detail = $detail;
		}
		
		/**
		 * @param int $code
		 */
		public function setCode($code) {
			$this->code = $code;
			$this->validateRequiredParameters();
		}
		
		/**
		 * @param string $title
		 */
		public function setTitle($title) {
			$this->title = $title;
			$this->validateRequiredParameters();
		}
		
		/**
		 * @param MetaObject $meta
		 */
		public function setMeta(MetaObject $meta) {
			$this->meta = $meta;
		}
		
		/**
		 * @return MetaObject
		 */
		public function getMeta() {
			return $this->meta;
		}
	}
