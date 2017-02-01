<?php
	/**
	 * Trait RegistersUsers
	 *
	 * @package EchoIt\JsonApi\Auth
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Auth;
	
	use EchoIt\JsonApi\Data\TopLevelObject;
	use Illuminate\Contracts\Auth\Guard;
	use Illuminate\Http\Request;
	use Illuminate\Contracts\Auth\Authenticatable;
	use Illuminate\Auth\Events\Registered;
	use Illuminate\Support\Facades\Auth;
	
	trait RegistersUsers {
		use \Illuminate\Foundation\Auth\RegistersUsers;
		
		/**
		 * Registers a new user
		 * @param Authenticatable $user
		 */
		public function registerUser(Request $request, Authenticatable $user) {
			event(new Registered($user));
			
			$this->guard($request)->login($user, true);
		}
		
		protected function userRegistered(Request $request, Authenticatable $user, TopLevelObject $topLevelObject) {
			/** @var Guard $guard */
			$guard = $this->guard($request);
			if ($guard instanceof TokenGuard === true) {
				/** @var TokenGuard $guard */
				$guard->addTokenToResponse($topLevelObject, $user);
			}
		}
		
		public function guard(Request $request) {
			return Auth::guard($request->getGuardType());
		}
	}
