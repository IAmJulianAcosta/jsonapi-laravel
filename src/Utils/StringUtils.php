<?php
	/**
	 * Class StringUtils
	 *
	 * @package IAmJulianAcosta\JsonApi\Utils
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Utils;
	
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
		public static function genreateMemberName ($key) {
			$key = s($key)->replace('@', '')->slugify()->dasherize()->slugify();
			
			return $key->__toString();
		}
		
		/**
		 * By default laravel uses camelCase as relationship names, but we're using hyphens by default in API
		 *
		 * @param $relationshipName
		 *
		 * @return string
		 */
		public static function camelizeRelationshipName ($relationshipName) {
			return s($relationshipName)->camelize()->__toString();
		}
		
	}
