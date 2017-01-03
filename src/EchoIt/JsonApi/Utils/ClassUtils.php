<?php
	/**
	 * Class Utils
	 *
	 * @package EchoIt\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi\Utils;
	
	use Illuminate\Support\Pluralizer;
	use function Stringy\create as s;

	class ClassUtils {
		/**
		 * Generates controller class name with namespace
		 *
		 * @param      $controllerShortName string The name of the model (in plural)
		 *
		 * @param bool $isPlural
		 * @param bool $short
		 *
		 * @return string Class name of related resource
		 */
		public static function getControllerFullClassName ($controllerShortName, $namespace, $isPlural = true, $short = false) {
			$controllerShortName = s ($controllerShortName)->camelize()->__toString();
			
			if ($isPlural) {
				$controllerShortName = Pluralizer::singular ($controllerShortName);
			}
			
			return (!$short ? $namespace . '\\' : "") . ucfirst ($controllerShortName) . 'Controller';
		}
		
		/**
		 * Generates controller short class name
		 *
		 * @return string
		 */
		public static function getControllerShortClassName($controllerClass) {
			$class = explode('\\', $controllerClass);
			
			return array_pop($class);
		}
		
		/**
		 * Convert HTTP method to it's controller method counterpart.
		 *
		 * @param  string $method HTTP method
		 *
		 * @return string
		 */
		public static function methodHandlerName($method) {
			return 'handle' . ucfirst(strtolower($method));
		}
		
	}
