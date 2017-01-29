<?php
	/**
	 * Class TopLevelObject
	 *
	 * @package EchoIt\JsonApi\Data
	 * @author Julian Acosta <iam@julianacosta.me>
	 */
	namespace EchoIt\JsonApi\Data;
	
	use EchoIt\JsonApi\Exception;
	use EchoIt\JsonApi\Http\Response;
	use Illuminate\Support\Collection;
	
	class TopLevelObject extends JSONAPIDataObject {
		
		/**
		 * @var Collection|TopLevelObject
		 */
		protected $data;
		
		/**
		 * @var array
		 */
		protected $errors;
		
		/**
		 * @var MetaObject
		 */
		protected $meta;
		
		/**
		 * @var array
		 */
		protected $included;
		
		/**
		 * @var array
		 */
		protected $links;
		
		/**
		 * TopLevelObject constructor.
		 *
		 * @param ResourceObject|Collection|null $data
		 * @param Collection                     $errors
		 * @param MetaObject|null                $meta
		 * @param Collection|null                $included
		 * @param LinksObject                    $links
		 */
		public function __construct($data = null, $errors = null, MetaObject $meta = null, Collection $included = null,
			LinksObject $links = null
		) {
			$this->data     = $data;
			$this->errors   = $errors;
			$this->meta     = $meta;
			$this->included = $included;
			$this->links    = $links;
			$this->validateRequiredParameters();
		}
		
		protected function validateRequiredParameters() {
			$this->validatePresence($this->data, $this->errors, $this->meta);
			$this->validateCoexistence($this->data, $this->errors);
		}
		
		private function validatePresence($data, $errors, $meta) {
			if (empty ($data) === true && empty($errors) === true && empty ($meta) === true) {
				Exception::throwSingleException(
					"Either 'data' 'errors' or 'meta' object must be present on JSON API top level object",
					ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0
				);
			}
		}
		
		private function validateCoexistence($data, $errors) {
			if (empty ($data) === false && empty($errors) === false) {
				Exception::throwSingleException(
					"'data' and 'errors' object must be present on JSON API top level object",
					ErrorObject::LOGIC_ERROR, Response::HTTP_UNPROCESSABLE_ENTITY
				);
			}
		}
		
		public function jsonSerialize () {
			$returnArray = [
				"jsonapi" => [
					"version" => "1.0"
				]
			];
			
			$this->pushInstanceObjectToReturnArray($returnArray, "data");
			$this->pushInstanceObjectToReturnArray($returnArray, "errors");
			$this->pushInstanceObjectToReturnArray($returnArray, "meta");
			$this->pushInstanceObjectToReturnArray($returnArray, "included");
			$this->pushInstanceObjectToReturnArray($returnArray, "links");
			
			return $returnArray;
		}
		
		protected function pushInstanceObjectToReturnArray (&$returnArray, $key) {
			if ($key === "included") {
				if (empty($this->data) === true && empty($this->errors) && empty($this->meta)) {
					Exception::throwSingleException
					("Trying to send included resources, but no data is present",
								ErrorObject::LOGIC_ERROR, Response::HTTP_UNPROCESSABLE_ENTITY, 0
					);
				}
			}
			parent::pushInstanceObjectToReturnArray($returnArray, $key);
		}
		
		public function isEmpty() {
			return empty ($this->data) && empty($this->errors) && ($this->meta);
		}
		
		/**
		 * @return Collection|ResourceObject
		 */
		public function getData() {
			return $this->data;
		}
		
		/**
		 * @param Collection|ResourceObject $data
		 */
		public function setData($data) {
			$this->data = $data;
			$this->validateRequiredParameters();
		}
		
		/**
		 * @return array
		 */
		public function getErrors() {
			return $this->errors;
		}
		
		/**
		 * @param array $errors
		 */
		public function setErrors($errors) {
			$this->errors = $errors;
			$this->validateRequiredParameters();
		}
		
		/**
		 * @return MetaObject
		 */
		public function getMeta() {
			return $this->meta;
		}
		
		/**
		 * @param MetaObject $meta
		 */
		public function setMeta(MetaObject $meta) {
			$this->meta = $meta;
			$this->validateRequiredParameters();
		}
		
		/**
		 * @return array
		 */
		public function getIncluded() {
			return $this->included;
		}
		
		/**
		 * @param array $included
		 */
		public function setIncluded($included) {
			$this->included = $included;
		}
		
		/**
		 * @return array
		 */
		public function getLinks() {
			return $this->links;
		}
		
		/**
		 * @param array $links
		 */
		public function setLinks($links) {
			$this->links = $links;
		}
	}
