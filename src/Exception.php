<?php namespace IAmJulianAcosta\JsonApi;

use IAmJulianAcosta\JsonApi\Data\ErrorObject;
use IAmJulianAcosta\JsonApi\Data\MetaObject;
use IAmJulianAcosta\JsonApi\Routing\Controller;
use IAmJulianAcosta\JsonApi\Http\ErrorResponse;
use Illuminate\Support\Collection;

/**
 * JsonApi\Exception represents an Exception that can be thrown where a JSON response may be expected.
 *
 * @author Julián Acosta <iam@julianacosta.me>
 */
class Exception extends \Exception {
	/**
	 * @var Collection
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
	 * @var string
	 */
	protected $errorMessage;
	
	/**
	 * Exception constructor.
	 *
	 * @param Collection $errors
	 */
    public function __construct(Collection $errors) {
	    $this->multipleErrors = $errors->count() > 1;
	    if ($this->multipleErrors === true) {
		    $this->httpErrorCode  = ErrorResponse::HTTP_BAD_REQUEST;
		    $this->errorCode      = ErrorObject::MULTIPLE_ERRORS;
		    $this->errorMessage   = "Bad request";
	    }
	    else {
		    /** @var ErrorObject $error */
		    $error                = $errors->get(0);
		    $this->httpErrorCode  = $error->getStatus();
		    $this->errorCode      = $error->getCode();
		    $this->errorMessage   = $error->getDetail();
	    }
	    $this->errors = $errors;
	    parent::__construct($this->errorMessage, $this->errorCode );
    }
	
	/**
	 * @param            $title
	 * @param            $code
	 * @param            $status
	 * @param int        $resourceIdentifier
	 *
	 * @param            $detail
	 * @param MetaObject $meta
	 *
	 * @throws Exception
	 */
	public static function throwSingleException($title, $code, $status, $resourceIdentifier = Controller::ERROR_SCOPE,
		$detail = "", MetaObject $meta = null
	) {
		throw new static(
			new Collection(
				[
					new ErrorObject ($title, $code, $status, $resourceIdentifier, $detail, $meta)
				]
			)
		);
	}
	
	/**
     * This method returns a HTTP response representation of the Exception
     *
     * @return \IAmJulianAcosta\JsonApi\Http\ErrorResponse
     */
    public function response() {
	    return new ErrorResponse($this->errors, $this->httpErrorCode);
    }
}
