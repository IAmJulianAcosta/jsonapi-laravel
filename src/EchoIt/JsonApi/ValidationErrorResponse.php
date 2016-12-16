<?php namespace EchoIt\JsonApi;

use EchoIt\JsonApi\Validation\ValidationError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ValidationErrorResponse represents a HTTP error response containing multiple errors with a JSON API compliant payload.
 *
 * @author Julián Acosta <iam@julianacosta.me>
 */
class ValidationErrorResponse extends ErrorResponse {
	/**
	 * ValidationErrorResponse constructor.
	 *
	 * @param array $errors
	 * @param int   $httpStatusCode
	 */
    public function __construct(array $errors, $httpStatusCode = Response::HTTP_BAD_REQUEST) {
        parent::__construct ($errors, $httpStatusCode);
    }
	
	/**
	 * @param Error $error
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function generateErrorObject(Error $error) {
		if ($error instanceof ValidationError) {
			return [
				'id'        => microtime(),
				'code'      => (string)$error->getErrorCode(),
				'title'     => (string)$error->getTitle(),
				'detail'    => (string)$error->getMessage(),
				'status'    => (string)$error->getHttpErrorCode(),
				'links'     => [
					'about' => 'https://laravel.com/docs/5.3/validation#available-validation-rules'
				],
				'parameter' => $error->getAttribute()
			];
		}
		else {
			throw new \Exception('$error must be a ValidationError');
		}
	}
}
