<?php
	/**
	 * Class TokenGuard
	 *
	 * @package EchoIt\JsonApi\Auth
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Auth;
	
	use Illuminate\Http\Request;
	
	use Illuminate\Auth\TokenGuard as BaseTokenGuard;
	
	class TokenGuard extends BaseTokenGuard {
		
		private static $realm = "Token";
		
		/**
		 * Get the token for the current request.
		 *
		 * @return string
		 */
		public function getTokenForRequest() {
			return static::parseMobileAuth($this->request);
		}
		
		public static function parseMobileAuth(Request $request) {
			$matches = array ();
			if (preg_match("/^(Basic\srealm=\"){1}([a-zA-Z]+)\"$/", $request->header("WWW-Authenticate"), $matches)) {
				if (count($matches) >= 3) {
					$realm = $matches[2];
					if ($realm === static::$realm) {
						if (preg_match("/^(Basic\s){1}([\|\-\_\.\\\@0-9a-zA-Z]+)$/", $request->header("Authorization"),
							$matches)) {
							if (count($matches) >= 3) {
								return $matches[2];
							}
						}
					}
				}
			}
			
			return null;
		}
	}
