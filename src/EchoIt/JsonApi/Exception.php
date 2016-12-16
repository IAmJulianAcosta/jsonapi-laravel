<?php namespace EchoIt\JsonApi;

use Illuminate\Http\Response;

/**
 * JsonApi\Exception represents an Exception that can be thrown where a JSON response may be expected.
 *
 * @author Julián Acosta <iam@julianacosta.me>
 */
class Exception extends \Exception {
	/**
	 * @var array
	 */
	protected $errors;
	
	/**
	 * @var int
	 */
	protected $httpErrorCode;
	
	/**
	 * @var int
	 */
	protected $errorCode;
	
	/**
	 *
	 */
	protected $multipleErrors;
	
	/**
	 * Exception constructor.
	 *
	 * @param array $errors
	 */
    public function __construct(array $errors) {
	    $this->multipleErrors = count($errors) > 1;
	    if ($this->multipleErrors === true) {
		    $this->httpErrorCode = Response::HTTP_BAD_REQUEST;
		    $this->errorCode      = Error::MULTIPLE_ERRORS;
		    $this->errorMessage   = "Bad request";
	    }
	    else {
		    /** @var Error $error */
		    $error                = $errors [0];
		    $this->httpErrorCode  = $error->getHttpErrorCode();
		    $this->errorCode      = $error->getErrorCode();
		    $this->errorMessage   = $error->getMessage();
	    }
	    $this->errors = $errors;
	    parent::__construct("Bad request", $this->errorCode );
    }

    /**
     * This method returns a HTTP response representation of the Exception
     *
     * @return \EchoIt\JsonApi\ErrorResponse
     */
    public function response() {
        return new ErrorResponse($this->errors, $this->httpErrorCode);
    }
}
