<?php
	/**
	 * Trait RegistersUsers
	 *
	 * @package EchoIt\JsonApi\Auth
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Auth;
	
	use Illuminate\Http\Request;
	use Illuminate\Contracts\Auth\Authenticatable;
	use Illuminate\Auth\Events\Registered;
	
	trait RegistersUsers {
		use \Illuminate\Foundation\Auth\RegistersUsers;
		
		/**
		 * Registers a new user
		 * @param Authenticatable $user
		 */
		public function registerUser(Authenticatable $user) {
			event(new Registered($user));
			
			$this->guard()->login($user, true);
		}
	}
