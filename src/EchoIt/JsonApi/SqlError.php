<?php
	/**
	 * Class SqlError
	 *
	 * @package EchoIt\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi;
	
	use Illuminate\Database\QueryException;
	
	class SqlError extends Error {
		
		/** @var QueryException */
		protected $exception;
		
		public function __construct($title, $errorCode, $httpErrorCode, QueryException $exception, $resourceIdentifier = 0) {
			parent::__construct($title, $errorCode, $httpErrorCode, $resourceIdentifier);
			$this->exception = $exception;
			if (config("app.debug") === true) {
				$this->setMessage($exception->getMessage());
				$this->setAdditionalAttributes($this->generateAdditionalAttributes());
			}
		}
		
		protected function generateAdditionalAttributes() {
			return [
				"sql"      => $this->exception->getSql(),
				"bindings" => $this->exception->getBindings(),
				"file"     => $this->exception->getFile(),
				"line"     => $this->exception->getLine(),
				"sqlCode"  => $this->exception->getCode(),
			];
		}
	}
