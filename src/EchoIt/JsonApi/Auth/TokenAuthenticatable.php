<?php
	
	namespace EchoIt\JsonApi;
	
	use Illuminate\Auth\Authenticatable;
	
	/**
	 * Class TokenAuthenticatable
	 *
	 * @package EchoIt\JsonApi
	 * @property string api_token
	 */
	trait TokenAuthenticatable {
		use Authenticatable;
	}
