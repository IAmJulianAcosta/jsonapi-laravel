<?php namespace EchoIt\JsonApi\Http;

use Illuminate\Http\JsonResponse;

/**
 * This class contains the parameters to return in the response to an API request.
 *
 * @property array $included included resources
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
	 * Response constructor.
	 *
	 * @param mixed|null $jsonApiData
	 * @param int        $httpStatusCode
	 * @param array      $headers
	 * @param int        $options
	 */
	public function __construct($jsonApiData, $httpStatusCode = 200, $headers = [], $options = 0) {
		$this->jsonApiData    = $jsonApiData;
		$this->httpStatusCode = $httpStatusCode;
		parent::__construct(
			$this->generateData(),
			$this->httpStatusCode,
			array_merge(['Content-Type' => 'application/vnd.api+json'], $headers),
			$options
		);
	}

	/**
	 * Used to set or overwrite a parameter.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function __set($key, $value) {
		if ($key === 'data' || $key === 'jsonApiData') {
			$this->{$key} = $value;
		}
		else {
			$this->responseData[$key] = $value;
		}
		$this->setData($this->generateData());
	}
	
	/**
	 * Adds a parameter to response "data" key.
	 *
	 * @param $key
	 * @param $value
	 */
	public function addToJsonApiData ($key, $value) {
		$this->jsonApiData[$key] = $value;
	}
	
	/**
	 * @return array
	 */
	protected function generateData() {
		return array_merge(['data' => $this->jsonApiData], array_filter($this->responseData));
	}
}
