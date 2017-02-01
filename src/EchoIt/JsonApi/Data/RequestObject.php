<?php
	/**
	 * Class RequestObject
	 *
	 * @package EchoIt\JsonApi\Data
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Data;
	
	use EchoIt\JsonApi\Exception;
	use EchoIt\JsonApi\Http\Request;
	use EchoIt\JsonApi\Http\Response;
	use Illuminate\Support\Pluralizer;
	
	class RequestObject extends JSONAPIDataObject {
		
		/**
		 * @var array
		 */
		protected $content;
		
		/**
		 * @var Request
		 */
		protected $request;
		
		/**
		 * @var string
		 */
		protected $dataType;
		
		/**
		 * @var array
		 */
		protected $data;
		
		/**
		 * @var integer
		 */
		protected $id;
		
		/**
		 * @var string
		 */
		protected $type;
		
		/**
		 * @var array
		 */
		protected $attributes;
		
		/**
		 * @var array
		 */
		protected $relationships;
		
		public function __construct(array $content, Request $request) {
			$this->content = $content;
			$this->request = $request;
		}
		
		public function validateRequiredParameters() {
			if ($this->request->shouldHaveContent()) {
				$content = $this->content;
				$this->validateContent($content);
				$data = $content['data'];
				
				$this->validateType($data);
				$this->validateId($data);
			}
		}
		
		public function extractData () {
			$content = $this->content;
			$this->data = $data = $content['data'];
			$this->id = array_key_exists('id', $data) ? $data['id'] : null;
			$this->type = array_key_exists('type', $data) ? $data['type'] : null;
			$this->attributes = array_key_exists('attributes', $data) ? $data['attributes'] : null;
			$this->relationships = array_key_exists('relationships', $data) ? $data['relationships'] : null;
		}
		
		/**
		 * @param $content
		 */
		private function validateContent($content) {
			if (isset ($content) === false || is_array($content) === false || array_key_exists('data', $content) === false) {
				Exception::throwSingleException('Payload either contains misformed JSON or missing "data" parameter.',
					ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_BAD_REQUEST);
			}
		}
		
		/**
		 * @param $data
		 */
		private function validateType ($data) {
			if (array_key_exists("type", $data) === false) {
				Exception::throwSingleException(
					'"type" parameter not set in request.', ErrorObject::INVALID_ATTRIBUTES,
					Response::HTTP_BAD_REQUEST
				);
			}
			if ($data['type'] !== $type = Pluralizer::plural($this->dataType)) {
				Exception::throwSingleException(
					sprintf('"type" parameter is not valid. Expecting %s, %s given', $type, $data['type']),
					ErrorObject::INVALID_ATTRIBUTES, Response::HTTP_CONFLICT
				);
			}
		}
		
		/**
		 * @param $data
		 */
		private function validateId($data) {
			if ($this->request->getMethod() === 'POST' && isset($data['id']) === false) {
				Exception::throwSingleException('"id" parameter not set in request.', ErrorObject::INVALID_ATTRIBUTES,
					Response::HTTP_BAD_REQUEST);
			}
		}
		
		/**
		 * @return array
		 */
		public function getContent() {
			return $this->content;
		}
		
		/**
		 * @param array $content
		 */
		public function setContent($content) {
			$this->content = $content;
		}
		
		/**
		 * @return Request
		 */
		public function getRequest() {
			return $this->request;
		}
		
		/**
		 * @param Request $request
		 */
		public function setRequest($request) {
			$this->request = $request;
		}
		
		/**
		 * @return mixed
		 */
		public function getDataType() {
			return $this->dataType;
		}
		
		/**
		 * @param mixed $dataType
		 */
		public function setDataType($dataType) {
			$this->dataType = $dataType;
		}
		
		/**
		 * @return array
		 */
		public function getData() {
			return $this->data;
		}
		
		/**
		 * @param array $data
		 */
		public function setData($data) {
			$this->data = $data;
		}
		
		/**
		 * @return int
		 */
		public function getId() {
			return $this->id;
		}
		
		/**
		 * @param int $id
		 */
		public function setId($id) {
			$this->id = $id;
		}
		
		/**
		 * @return string
		 */
		public function getType() {
			return $this->type;
		}
		
		/**
		 * @param string $type
		 */
		public function setType($type) {
			$this->type = $type;
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