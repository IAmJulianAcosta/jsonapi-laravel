<?php
	/**
	 * Class ExceptionHandler
	 *
	 * @package EchoIt\JsonApi\Exceptions
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Exceptions;
	
	use Illuminate\Foundation\Exceptions\Handler as BaseHandler;
	
	class ExceptionHandler extends BaseHandler {
		/**
		 * Render an exception into an HTTP response.
		 *
		 * @param  \Illuminate\Http\Request  $request
		 * @param  \Exception  $exception
		 * @return \Illuminate\Http\Response
		 */
		public function render($request, \Exception $exception) {
			$caller = debug_backtrace()[1]['function'];
			if ($caller === "handleException") {
				if ($exception instanceof \EchoIt\JsonApi\Exception) {
					$response = $exception->response();
					return $response;
				}
				return parent::render($request, $exception);
			}
		}
	}
