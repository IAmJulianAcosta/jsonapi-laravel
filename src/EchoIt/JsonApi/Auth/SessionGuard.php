<?php
	/**
	 * Class SessionGuard
	 *
	 * @package EchoIt\JsonApi\Auth
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Auth;
	
	use Illuminate\Contracts\Auth\Authenticatable;
	use Illuminate\Contracts\Auth\UserProvider;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Session\SessionInterface;
	
	class SessionGuard extends \Illuminate\Auth\SessionGuard {
		
		protected $authCookie;
		
		public function __construct($name, UserProvider $provider, SessionInterface $session, Request $request = null, $authCookie = "") {
			parent::__construct($name, $provider, $session, $request);
			$authCookie = empty($authCookie) === false ? $authCookie : $name . "_cookie";
			$this->authCookie = $authCookie;
		}
		
		/**
		 * Create a "remember me" cookie for a given ID.
		 *
		 * @param  string  $value
		 * @return \Symfony\Component\HttpFoundation\Cookie
		 */
		protected function createRecaller($value) {
			return $this->getCookieJar()->forever($this->getRecallerName(), $value, null, null, false, false);
		}
		
		public function getRecallerName() {
			return $this->authCookie;
		}
		
		/**
		 * Log a user into the application.
		 *
		 * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
		 * @param  bool  $remember
		 * @return void
		 */
		public function login(Authenticatable $user, $remember = false) {
			$this->updateSession($user->getAuthIdentifier());
			
			// If the user should be permanently "remembered" by the application we will
			// queue a permanent cookie that contains the encrypted copy of the user
			// identifier. We will then decrypt this later to retrieve the users.
			if ($remember) {
				$this->createRememberTokenIfDoesntExist($user);
				
				$this->queueRecallerCookie($user);
			}
			
			// If we have an event dispatcher instance set we will fire an event so that
			// any listeners will hook into the authentication events and run actions
			// based on the login and logout events fired from the guard instances.
			$this->fireLoginEvent($user, $remember);
			
			$this->setUser($user);
		}
	}
