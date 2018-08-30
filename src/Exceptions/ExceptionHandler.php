<?php
/**
 * Class ExceptionHandler
 *
 * @package IAmJulianAcosta\JsonApi\Exceptions
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as BaseHandler;
use Illuminate\Http\Response;

class ExceptionHandler extends BaseHandler {
  /**
   * Render an exception into an HTTP response.
   *
   * @param  \Illuminate\Http\Request $request
   * @param  \Exception               $exception
   *
   * @return \Illuminate\Http\Response
   */
  public function render($request, \Exception $exception) {
    $caller = debug_backtrace()[1]['function'];
    if ($exception instanceof \IAmJulianAcosta\JsonApi\Exception) {
      if ($caller === "renderHttpResponse") {
        return new Response("", 500);
      } else {
        $response = $exception->response();
      }
      return $response;
    }
    return parent::render($request, $exception);
  }
}
