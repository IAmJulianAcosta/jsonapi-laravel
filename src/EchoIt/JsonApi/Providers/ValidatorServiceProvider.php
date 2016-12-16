<?php
	/**
	 * Class ValidatorServiceProvider
	 *
	 * @package EchoIt\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Providers;
	
	use EchoIt\JsonApi\Validation\Validator;
	use Illuminate\Support\ServiceProvider;
	
	class ValidatorServiceProvider extends ServiceProvider {
		
		public function boot() {
			\Validator::resolver(
				function($translator, $data, $rules, $messages) {
					return new Validator($translator, $data, $rules, $messages);
				}
			);
		}
		
		public function register() {
		}
	}
