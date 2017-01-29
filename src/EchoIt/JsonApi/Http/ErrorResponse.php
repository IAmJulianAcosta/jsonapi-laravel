<?php
	namespace EchoIt\JsonApi\Http;
	
	use EchoIt\JsonApi\Data\TopLevelObject;
	use Illuminate\Support\Collection;
	
	/**
	 * ErrorResponse represents a HTTP error response with a JSON API compliant payload.
	 *
	 * @author Julián Acosta <iam@julianacosta.me>
	 */
	class ErrorResponse extends Response {
		/**
		 * ErrorResponse constructor.
		 *
		 * @param Collection $errors
		 * @param int   $httpErrorCode
		 */
		public function __construct(Collection $errors, $httpErrorCode = self::HTTP_BAD_REQUEST) {
			parent::__construct(new TopLevelObject(null, $errors), $httpErrorCode);
		}
		
	}
