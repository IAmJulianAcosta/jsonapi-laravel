<?php

namespace IAmJulianAcosta\JsonApi\Auth\Middleware;

use \Closure;
use IAmJulianAcosta\JsonApi\Http\Request;

class GuardType {

  public function handle(Request $request, Closure $next, $guard) {
    $request->setGuardType($guard);
    return $next($request);
  }
}
