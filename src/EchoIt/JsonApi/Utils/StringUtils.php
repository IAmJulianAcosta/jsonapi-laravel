<?php
	/**
	 * Class StringUtils
	 *
	 * @package EchoIt\JsonApi\Utils
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Utils;
	
	use function Stringy\create as s;
	
	class StringUtils {
		
		/**
		 * @param $resourceName
		 *
		 * @return string
		 */
		public static function dasherizedResourceName($resourceName) {
			return s($resourceName)->dasherize()->__toString();
		}
	}
