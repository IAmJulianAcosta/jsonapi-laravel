<?php
	
	namespace EchoIt\JsonApi\Auth\Middleware;
	
	use \Closure;
	use EchoIt\JsonApi\Http\Request;
	
	class GuardType {
		
		public function handle(Request $request, Closure $next, $guard) {
			$request->setGuardType($guard);
			return $next($request);
		}
	}
