<?php
	/**
	 * Trait RegistersUsers
	 *
	 * @package EchoIt\JsonApi\Auth
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Auth;
	
	use Illuminate\Contracts\Auth\Authenticatable;
	use Illuminate\Auth\Events\Registered;
	
	trait RegistersUsers {
		use \Illuminate\Foundation\Auth\RegistersUsers;
		/**
		 * @param Authenticatable $user
		 */
		public function register(Authenticatable $user) {
			event(new Registered($user));
			
			$this->guard()->login($user, true);
		}
	}
