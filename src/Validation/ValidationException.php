<?php
	namespace IAmJulianAcosta\JsonApi\Validation;
	
	use IAmJulianAcosta\JsonApi\Exception;
	
	/**
	 * Validation represents an Exception that can be thrown in the event of a validation failure where a JSON response may be expected.
	 *
	 * @author Julián Acosta <iam@julianacosta.me>
	 */
	class ValidationException extends Exception {
		
		/**
		 * This method returns a HTTP response representation of the Exception
		 *
		 * @return ValidationErrorResponse
		 */
		public function response() {
			return new ValidationErrorResponse($this->errors, $this->httpErrorCode);
		}
	}
