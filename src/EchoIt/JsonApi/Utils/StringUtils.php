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
		
		/**
		 * @param array $attributes
		 */
		public static function normalizeAttributes(array &$attributes) {
			foreach ($attributes as $key => $value) {
				if (is_string($key)) {
					unset ($attributes[$key]);
					$attributes[s($key)->underscored()->__toString()] = $value;
				}
			}
		}
		
		/**
		 * @param $key
		 *
		 * @return string
		 */
		public static function dasherizeKey ($key) {
			return s($key)->dasherize()->__toString();
		}
		
	}
