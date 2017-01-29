<?php
	/**
	 * Class SqlError
	 *
	 * @package EchoIt\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi;
	
	use EchoIt\JsonApi\Data\ErrorObject;
	use EchoIt\JsonApi\Data\MetaObject;
	use Illuminate\Database\QueryException;
	use Illuminate\Support\Collection;
	
	class SqlError extends ErrorObject {
		
		/** @var QueryException */
		protected $exception;
		
		public function __construct($title, $errorCode, $httpErrorCode, QueryException $exception, $resourceIdentifier = 0) {
			parent::__construct($title, $errorCode, $httpErrorCode, $resourceIdentifier);
			$this->exception = $exception;
			if (config("app.debug") === true) {
				$this->setDetail($exception->getMessage());
				$this->setMeta($this->generateAdditionalAttributes());
			}
		}
		
		protected function generateAdditionalAttributes() {
			return new MetaObject(
					new Collection(
						[
							"sql"      => $this->exception->getSql(),
							"bindings" => $this->exception->getBindings(),
							"file"     => $this->exception->getFile(),
							"line"     => $this->exception->getLine(),
							"sqlCode"  => $this->exception->getCode(),
						]
					)
			);
		}
	}
