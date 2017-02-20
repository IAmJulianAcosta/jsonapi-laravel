<?php
	/**
	 * Class Utils
	 *
	 * @package IAmJulianAcosta\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Utils;
	
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
			$controllerShortName = s($controllerShortName)->camelize()->__toString();
			
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
		
		/**
		 * Generates model class name Default output: Path\To\Model\ModelName
		 *
		 * @param string $modelName The name of the model
		 * @param bool $isPlural If is needed to convert this to singular
		 * @param bool $short Should return short name (without namespace)
		 * @param bool $toLowerCase Should return lowered case model name
		 * @param bool $capitalizeFirst
		 *
		 * @return string Class name of related resource
		 */
		public static function getModelClassName (
			$modelName, $namespace, $isPlural = true, $short = false, $toLowerCase = false, $capitalizeFirst = true
		) {
			if ($isPlural) {
				$modelName = Pluralizer::singular ($modelName);
			}
			
			$className = "";
			if ($short === false) {
				$className .= $namespace . '\\';
			}
			$className .= $toLowerCase ? strtolower ($modelName) : ucfirst ($modelName);
			$className = $capitalizeFirst ? s($className)->upperCamelize ()->__toString () : s($className)->camelize ()->__toString ();
			
			return $className;
		}
		
	}
