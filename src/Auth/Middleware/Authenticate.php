<?php
	/**
	 * Class Authenticate
	 *
	 * @package IAmJulianAcosta\JsonApi\Auth\Middleware
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Auth\Middleware;
	
	use IAmJulianAcosta\JsonApi\Data\ErrorObject;
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\Response;
	use \Closure;
	
	class Authenticate extends \Illuminate\Auth\Middleware\Authenticate {
		public function handle($request, Closure $next, ...$guards) {
			$user = $this->authenticate($guards);
			
			if (is_null($user) === true) {
				Exception::throwSingleException(
					"Invalid API key or not provided, you are not logged in",
					ErrorObject::UNAUTHORIZED_ACCESS_TOKEN_PROVIDED, Response::HTTP_UNAUTHORIZED
				);
			}
			
			return $next($request);
		}
		
		protected function authenticate(array $guards) {
			if (empty($guards)) {
				return $this->auth->authenticate();
			}
			
			foreach ($guards as $guard) {
				if ($this->auth->guard($guard)->check()) {
					$this->auth->shouldUse($guard);
					
					return $this->auth->guard($guard)->user ();
				}
			}
			
			return null;
		}
	}
