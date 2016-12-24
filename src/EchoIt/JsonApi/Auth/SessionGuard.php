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
		
		protected $authCookieName;
		
		protected $authCookiePath;
		
		protected $authCookieDomain;
		
		protected $authCookieSecure;
		
		protected $authCookieHttpOnly;
		
		/**
		 * SessionGuard constructor.
		 *
		 * @param string           $name
		 * @param UserProvider     $provider
		 * @param SessionInterface $session
		 * @param Request|null     $request
		 * @param string           $authCookieName
		 * @param null             $authCookiePath
		 * @param null             $authCookieDomain
		 * @param null             $authCookieSecure
		 * @param null             $authCookieHttpOnly
		 */
		public function __construct($name, UserProvider $provider, SessionInterface $session, Request $request = null,
			$authCookieName = null, $authCookiePath = null, $authCookieDomain = null, $authCookieSecure = null,
			$authCookieHttpOnly = null) {
			parent::__construct($name, $provider, $session, $request);
			$authCookieName           = is_null($authCookieName) === false ? $authCookieName : $name . "_cookie";
			$this->authCookieName     = $authCookieName;
			$this->authCookiePath     = $authCookiePath;
			$this->authCookieDomain   = $authCookieDomain;
			$this->authCookieSecure   = $authCookieSecure;
			$this->authCookieHttpOnly = $authCookieHttpOnly;
		}
		
		/**
		 * Create a "remember me" cookie for a given ID.
		 *
		 * @param  string  $value
		 * @return \Symfony\Component\HttpFoundation\Cookie
		 */
		protected function createRecaller($value) {
			return $this->getCookieJar()->forever($this->getRecallerName(), $value, $this->authCookiePath,
				$this->authCookieDomain, $this->authCookieSecure, $this->authCookieHttpOnly);
		}
		
		public function getRecallerName() {
			return $this->authCookieName;
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
