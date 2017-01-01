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
	use Symfony\Component\HttpFoundation\Cookie;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Session\SessionInterface;
	
	class SessionGuard extends \Illuminate\Auth\SessionGuard {
		
		/**
		 * @var Cookie
		 */
		protected $recallerCookie;
		
		/**
		 * SessionGuard constructor.
		 *
		 * @param string           $name
		 * @param UserProvider     $provider
		 * @param SessionInterface $session
		 * @param Cookie           $recallerCookie
		 * @param Request|null     $request
		 */
		public function __construct($name, UserProvider $provider, SessionInterface $session, Cookie $recallerCookie, Request $request = null) {
			parent::__construct($name, $provider, $session, $request);
			$this->recallerCookie = $recallerCookie;
		}
		
		/**
		 * Create a "remember me" cookie for a given ID.
		 *
		 * @param  string  $value
		 * @return \Symfony\Component\HttpFoundation\Cookie
		 */
		protected function createRecaller($value) {
			$cookie = $this->recallerCookie;
			
			return $this->getCookieJar()->forever($cookie->getName(), $value, $cookie->getPath(), $cookie->getDomain(),
				$cookie->isSecure(), $cookie->isHttpOnly());
		}
		
		public function getRecallerName() {
			return $this->recallerCookie->getName();
		}
	}
