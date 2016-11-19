<?php
	/**
	 * Class Utils
	 *
	 * @package EchoIt\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	/**
	 * Created by PhpStorm.
	 * User: julian-acosta
	 * Date: 27/09/16
	 * Time: 3:47 PM
	 */
	
	namespace EchoIt\JsonApi;
	
	use Illuminate\Support\Pluralizer;
	use function Stringy\create as s;

	class Utils {
		/**
		 * Returns handler class name with namespace
		 *
		 * @param      $handlerShortName string The name of the model (in plural)
		 *
		 * @param bool $isPlural
		 * @param bool $short
		 *
		 * @return string Class name of related resource
		 */
		public static function getHandlerFullClassName ($handlerShortName, $namespace, $isPlural = true, $short = false) {
			$handlerShortName = s ($handlerShortName)->camelize()->__toString();
			
			if ($isPlural) {
				$handlerShortName = Pluralizer::singular ($handlerShortName);
			}
			
			return (!$short ? $namespace . '\\' : "") . ucfirst ($handlerShortName) . 'Handler';
		}
		
		/**
		 * Returns handler short class name
		 *
		 * @return string
		 */
		public static function getHandlerShortClassName($handlerClass) {
			$class = explode('\\', $handlerClass);
			
			return array_pop($class);
		}
		
		/**
		 * Convert HTTP method to it's handler method counterpart.
		 *
		 * @param  string $method HTTP method
		 *
		 * @return string
		 */
		public static function methodHandlerName($method) {
			return 'handle' . ucfirst(strtolower($method));
		}
	}
