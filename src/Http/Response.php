<?php
	namespace IAmJulianAcosta\JsonApi\Http;
	
	use IAmJulianAcosta\JsonApi\Data\TopLevelObject;
	use Illuminate\Http\JsonResponse;
	
	/**
	 * This class contains the parameters to return in the response to an API request.
	 *
	 * @property array $links    Resource related links
	 * @property array $errors   Errors during request
	 * @property array $included Included resources
	 * @property array $meta     Meta information
	 */
	class Response extends JsonResponse {
		/**
		 * An array of parameters.
		 *
		 * @var array
		 */
		protected $responseData = [];
		
		/**
		 * The main response.
		 *
		 * @var array|object
		 */
		protected $jsonApiData;
		
		/**
		 * HTTP status code
		 *
		 * @var int
		 */
		protected $httpStatusCode;
		
		/**
		 * @var TopLevelObject
		 */
		protected $topLevelObject;
		
		/**
		 * Response constructor.
		 *
		 * @param TopLevelObject $topLevelObject
		 * @param int            $httpStatusCode
		 * @param array          $headers
		 * @param int            $options
		 */
		public function __construct(TopLevelObject $topLevelObject, $httpStatusCode = 200, $headers = [], $options = 0) {
			$this->topLevelObject    = $topLevelObject;
			$this->httpStatusCode = $httpStatusCode;
			parent::__construct($topLevelObject, $this->httpStatusCode,
				array_merge(['Content-Type' => 'application/vnd.api+json'], $headers), $options);
		}
		
		/**
		 * @return TopLevelObject
		 */
		public function getTopLevelObject() {
			return $this->topLevelObject;
		}
		
		/**
		 * @param TopLevelObject $topLevelObject
		 */
		public function setTopLevelObject($topLevelObject) {
			$this->topLevelObject = $topLevelObject;
		}
	}
