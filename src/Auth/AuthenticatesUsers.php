<?php

namespace IAmJulianAcosta\JsonApi\Auth;

use IAmJulianAcosta\JsonApi\Data\ErrorObject;
use IAmJulianAcosta\JsonApi\Data\MetaObject;
use IAmJulianAcosta\JsonApi\Data\ResourceObject;
use IAmJulianAcosta\JsonApi\Data\TopLevelObject;
use IAmJulianAcosta\JsonApi\Exception;
use IAmJulianAcosta\JsonApi\Http\Request as JsonApiRequest;
use IAmJulianAcosta\JsonApi\Routing\Controller;
use Illuminate\Http\Request;
use IAmJulianAcosta\JsonApi\Http\Response;
use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait AuthenticatesUsers {
  use \Illuminate\Foundation\Auth\AuthenticatesUsers;
  use ValidatesRequests;

  public function __construct(Request $request) {
    if (is_subclass_of(static::class, Controller::class)) {
      if (empty(static::$isAuthController) || static::$isAuthController === false) {
        throw new \LogicException("Auth controller subclasses must have defined isAuthController static property as true");
      }
      parent::__construct($request);
    } else {
      throw new \LogicException("AuthenticatesUsers trait must be used with JSON API controller, please 
				make your Auth controller a subclass of IAmJulianAcosta\\JsonApi\\Routing\\Controller");
    }
  }

  /**
   * @param Request $request
   *
   * @return Response
   * @throws Exception
   */
  public function login(Request $request) {
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

    Exception::throwSingleException(
      "An unknown error ocurred during login", ErrorObject::UNKNOWN_ERROR,
      Response::HTTP_INTERNAL_SERVER_ERROR
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
    $remember = isset($attributes["remember-me"]) ? !!$attributes["remember-me"] : false;

    return $this->guard()->attempt(
      $this->credentials($request), $remember
    );
  }

  /**
   * The user has been authenticated.
   *
   * @param Request $request
   * @param         $user
   *
   * @return Response
   * @throws Exception
   */
  protected function authenticated(Request $request, $user) {
    if ($user instanceof Model) {
      $topLevelObject = new TopLevelObject(new ResourceObject($user));
      /** @var Guard $guard */
      $guard = $this->guard();
      if ($guard instanceof TokenGuard && $user instanceof Authenticatable) {
        $guard->addTokenToResponse($topLevelObject, $user);
      }

      return new Response($topLevelObject, Response::HTTP_OK);
    } else {
      Exception::throwSingleException(
        'User passed is not a valid Model object', ErrorObject::SERVER_GENERIC_ERROR,
        Response::HTTP_BAD_REQUEST
      );
    }
  }

  /**
   * Get the needed authorization credentials from the request.
   *
   * @param Request $request
   *
   * @return array
   * @throws Exception
   */
  protected function credentials(Request $request) {
    $attributes = $this->getAttributes($request);

    return [
      "email" => $attributes["email"],
      "password" => $attributes["password"]
    ];
  }

  /**
   * Redirect the user after determining they are locked out.
   *
   * @param Request $request
   *
   * @throws Exception
   */
  protected function sendLockoutResponse(Request $request) {
    Exception::throwSingleException(
      'Too many failed attempts', ErrorObject::LOCKOUT, Response::HTTP_FORBIDDEN
    );
  }

  /**
   * Get the failed login response instance.
   *
   * @param Request $request
   *
   * @throws Exception
   */
  protected function sendFailedLoginResponse(Request $request) {
    Exception::throwSingleException('Invalid credentials', ErrorObject::INVALID_CREDENTIALS,
      Response::HTTP_UNAUTHORIZED
    );
  }

  /**
   * @param Request $request
   *
   * @return Response
   */
  public function logout(Request $request) {
    $this->guard()->logout();

    $request->session()->flush();

    $request->session()->regenerate();

    return $this->sendLogoutResponse($request);
  }

  /**
   * @param Request $request
   *
   * @return Response
   */
  protected function sendLogoutResponse(Request $request) {
    return new Response(
      new TopLevelObject(
        null,
        null,
        new MetaObject(
          new Collection(['message' => 'You have been logged out.'])
        )
      ),
      Response::HTTP_OK,
      ['Content-Type' => 'application/vnd.api+json']
    );
  }

  /**
   * @param Request $request
   *
   * @return mixed
   */
  protected function getAttributes(Request $request) {
    $request = JsonApiRequest::convertIlluminateRequestToJsonApiRequest($request);

    return $request->getJsonApiContent()->getAttributes();
  }

  public function guard() {
    /** @var \IAmJulianAcosta\JsonApi\Http\Request $request */
    $request = JsonApiRequest::convertIlluminateRequestToJsonApiRequest($this->request);
    $guardType = !is_null($request) ? $request->getGuardType() : null;

    return Auth::guard($guardType);
  }

}
