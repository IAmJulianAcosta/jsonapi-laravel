<?php namespace IAmJulianAcosta\JsonApi\Validation;

use IAmJulianAcosta\JsonApi\Http\ErrorResponse;
use Illuminate\Support\Collection;

/**
 * ValidationErrorResponse represents a HTTP error response containing multiple errors with a JSON API compliant payload.
 *
 * @author Julián Acosta <iam@julianacosta.me>
 */
class ValidationErrorResponse extends ErrorResponse {
  /**
   * ValidationErrorResponse constructor.
   *
   * @param Collection $errors
   * @param int        $httpStatusCode
   */
  public function __construct(Collection $errors, $httpStatusCode = self::HTTP_BAD_REQUEST) {
    parent::__construct($errors, $httpStatusCode);
  }

}
