<?php
/**
 * Class ValidatorServiceProvider
 *
 * @package IAmJulianAcosta\JsonApi
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Providers;

use IAmJulianAcosta\JsonApi\Validation\Validator;
use Illuminate\Support\ServiceProvider;

class ValidatorServiceProvider extends ServiceProvider {

  public function boot() {
    \Validator::resolver(
      function ($translator, $data, $rules, $messages) {
        return new Validator($translator, $data, $rules, $messages);
      }
    );
  }

  public function register() {
  }
}
