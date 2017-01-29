<?php
	/**
	 * Class TokenGuard
	 *
	 * @package EchoIt\JsonApi\Auth
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Auth;
	
	use EchoIt\JsonApi\Data\MetaObject;
	use EchoIt\JsonApi\Data\TopLevelObject;
	use EchoIt\JsonApi\Database\Eloquent\Model;
	use Illuminate\Contracts\Auth\Authenticatable;
	use Illuminate\Http\Request;
	
	use Illuminate\Auth\TokenGuard as BaseTokenGuard;
	use Illuminate\Support\Collection;
	use Illuminate\Support\Str;
	
	class TokenGuard extends BaseTokenGuard {
		
		const TYPE = "token";
		
		protected static $realm = "Token";
		
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
		
		public function addTokenToResponse (TopLevelObject $topLevelObject, Authenticatable $user) {
			$rememberToken = $user->getRememberToken();
			$topLevelObject->setMeta(new MetaObject(new Collection(["token" => $rememberToken])));
		}
		
		public function attempt (array $credentials = []) {
			/** @var Model $user */
			$user = $this->validateUser($credentials);
			if (is_null($user) === false) {
				$user->api_token = Str::random(60);
				$user->save ();
				return true;
			}
			return false;
		}
		
		/**
		 * @param array $credentials
		 *
		 * @return Authenticatable|null
		 */
		public function validateUser(array $credentials = []) {
			if ($user = $this->provider->retrieveByCredentials($credentials)) {
				$this->user = $user;
				
				return $user;
			}
			
			return null;
		}
		

	}
