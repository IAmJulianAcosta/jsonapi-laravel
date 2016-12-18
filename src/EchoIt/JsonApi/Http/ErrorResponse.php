<?php namespace EchoIt\JsonApi\Http;

use EchoIt\JsonApi\Error;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * ErrorResponse represents a HTTP error response with a JSON API compliant payload.
 *
 * @author Julián Acosta <iam@julianacosta.me>
 */
class ErrorResponse extends JsonResponse {
	/**
	 * ErrorResponse constructor.
	 *
	 * @param array $errors
	 * @param int   $httpStatusCode
	 */
    public function __construct(array $errors, $httpStatusCode = Response::HTTP_BAD_REQUEST) {
	    $data = [ 'errors' => [] ];
	
	    /** @var Error $error */
	    foreach ($errors as $error) {
		    $data['errors'][] = $this->generateErrorObject($error);
	    }
	    
	    parent::__construct ($data, $httpStatusCode);
    }
	
	/**
	 * @param $error
	 *
	 * @return array
	 */
	protected function generateErrorObject(Error $error) {
		return array_merge(
			[
				'id'     => (string) microtime(),
			    'code'   => (string) $error->getErrorCode(),
			    'title'  => (string) $error->getTitle(),
			    'detail' => (string) $error->getMessage(),
			    'status' => (string) $error->getHttpErrorCode(),
			],
			$error->getAdditionalAttributes()
		);
	}
}
