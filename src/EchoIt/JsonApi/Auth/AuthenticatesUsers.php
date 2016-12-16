<?php

	namespace EchoIt\JsonApi\Auth;
	
	use EchoIt\JsonApi\Error;
	use EchoIt\JsonApi\Exception;
	use EchoIt\JsonApi\Model;
	use Illuminate\Contracts\Validation\Validator;
	use Illuminate\Foundation\Validation\ValidatesRequests;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Http\Response;
	
	trait AuthenticatesUsers {
		use \Illuminate\Foundation\Auth\AuthenticatesUsers;
		use ValidatesRequests;
		
		/**
		 * @param Request $request
		 *
		 * @return Response
		 * @throws Exception
		 */
		public function login(Request $request) {
			//TODO use JSON Api request
			$this->validateLogin($request);
			
			// If the class is using the ThrottlesLogins trait, we can automatically throttle
			// the login attempts for this application. We'll key this by the username and
			// the IP address of the client making these requests into this application.
			if ($this->hasTooManyLoginAttempts($request)) {
				$this->fireLockoutEvent($request);
				
				$this->sendLockoutResponse($request);
			}
			
			if ($this->attemptLogin($request)) {
				return $this->sendLoginResponse($request);
			}
			
			// If the login attempt was unsuccessful we will increment the number of attempts
			// to login and redirect the user back to the login form. Of course, when this
			// user surpasses their maximum number of attempts they will get locked out.
			$this->incrementLoginAttempts($request);
			
			$this->sendFailedLoginResponse($request);
			
			throw new Exception(
				[
					new Error(
						"An unknown error ocurred during login",
						Error::UNKNOWN_ERROR,
						Response::HTTP_INTERNAL_SERVER_ERROR
					)
				]
			);
		}
		
		public function validate(Request $request, array $rules, array $messages = [], array $customAttributes = []) {
			/** @var Validator $validator */
			$validator = $this->getValidationFactory()->make($this->getAttributes($request), $rules, $messages, $customAttributes);
			
			if ($validator->fails()) {
				$this->throwValidationException($request, $validator);
			}
		}
		
		/**
		 * @param Request $request
		 *
		 * @return bool
		 */
		protected function attemptLogin(Request $request) {
			$attributes = $this->getAttributes($request);
			$remember   = isset($attributes["remember-me"]) ? !!$attributes["remember-me"] : false;
			
			return $this->guard()->attempt(
				$this->credentials($request), $remember
			);
		}
		
		
		/**
		 * @param Request $request
		 * @param         $user
		 *
		 * @return mixed
		 * @throws Exception
		 */
		protected function authenticated(Request $request, $user) {
			if ($user instanceof Model) {
				$response["meta"]  = [
					//TODO generate auth token?
				];
				$response["data"] = $user->toArray();
				return new JsonResponse($response, 200, ['Content-Type' => 'application/vnd.api+json']);
				
			}
			else {
				throw new Exception(
					[
						new Error (
							'User passed is not a valid Model object',
							Error::SERVER_GENERIC_ERROR,
							Response::HTTP_BAD_REQUEST
						)
					]
				);
			}
		}
		
		protected function credentials(Request $request) {
			if ($request->isJson() === false) {
				throw new Exception(
					[
						new Error (
							'Request must have a JSON API media type (application/vnd.api+json)',
							Error::MALFORMED_REQUEST,
							Response::HTTP_BAD_REQUEST
						)
					]
				);
			}
			$attributes = $this->getAttributes($request);
			
			return [
				"email" => $attributes["email"],
				"password" => $attributes["password"]
			];
		}
		
		protected function sendLockoutResponse (Request $request) {
			throw new Exception(
				[
					new Error (
						'Too many failed attempts',
						Error::LOCKOUT,
						Response::HTTP_FORBIDDEN
					)
				]
			);
		}
		
		protected function sendFailedLoginResponse (Request $request) {
			throw new Exception(
				[
					new Error (
						'Invalid credentials',
						Error::INVALID_CREDENTIALS,
						Response::HTTP_UNAUTHORIZED
					)
				]
			);
		}
		
		/**
		 * @param Request $request
		 *
		 * @return JsonResponse
		 */
		public function logout (Request $request) {
			$this->guard()->logout();
			
			$request->session()->flush();
			
			$request->session()->regenerate();
			
			return $this->sendLogoutResponse($request);
		}
		
		/**
		 * @param Request $request
		 *
		 * @return JsonResponse
		 */
		protected function sendLogoutResponse(Request $request) {
			return new JsonResponse(
				[
					'meta' => [
						'message' => 'You have been logged out.'
					]
				],
				200,
				['Content-Type' => 'application/vnd.api+json']
			);
		}
		
		/**
		 * @param Request $request
		 *
		 * @return mixed
		 */
		protected function getAttributes(Request $request) {
			$attributes = $request->all() ["data"]["attributes"];
			
			return $attributes;
		}
	}
