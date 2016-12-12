<?php
	/**
	 * Trait RegistersUsers
	 *
	 * @package EchoIt\JsonApi\Auth
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	/**
	 * Created by PhpStorm.
	 * User: julian-acosta
	 * Date: 11/29/16
	 * Time: 11:08 AM
	 */
	
	namespace EchoIt\JsonApi\Auth;
	
	use Illuminate\Http\Request;
	use Illuminate\Contracts\Auth\Authenticatable;
	use Illuminate\Auth\Events\Registered;
	
	trait RegistersUsers {
		use \Illuminate\Foundation\Auth\RegistersUsers;
		
		/**
		 * @param Request $request
		 * @param Authenticatable    $user
		 */
		public function register(Request $request, Authenticatable $user) {
			event(new Registered($user));
			
			$this->guard()->login($user, true);
		}
	}
	