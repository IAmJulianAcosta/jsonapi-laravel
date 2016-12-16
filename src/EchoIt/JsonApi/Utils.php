<?php
	/**
	 * Class Utils
	 *
	 * @package EchoIt\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi;
	
	use Illuminate\Http\Response;
	use Illuminate\Support\Collection;
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
		
		/**
		 * Iterate through result set to fetch the requested resources to include.
		 *
		 * @param Model $models
		 *
		 * @return array
		 */
		public static function getIncludedModels($models) {
			$modelsCollection = new Collection();
			$models           = $models instanceof Collection ? $models : [$models];
			
			/** @var Model $model */
			foreach ($models as $model) {
				$exposedRelations = $model->exposedRelations();
				
				foreach ($exposedRelations as $relationName) {
					$value = static::getModelsForRelation($model, $relationName);
					
					if (is_null($value)) {
						continue;
					}
					
					//Each one of the models relations
					/* @var Model $obj */
					foreach ($value as $obj) {
						// Check whether the object is already included in the response on it's ID
						$duplicate = false;
						$items     = $modelsCollection->where($obj->getPrimaryKey(), $obj->getKey());
						
						if (count($items) > 0) {
							foreach ($items as $item) {
								/** @var $item Model */
								if ($item->getResourceType() === $obj->getResourceType()) {
									$duplicate = true;
									break;
								}
							}
							if ($duplicate) {
								continue;
							}
						}
						
						//add type property
						$attributes = $obj->getAttributes();
						
						$obj->setRawAttributes($attributes);
						
						$modelsCollection->push($obj);
					}
				}
			}
			
			return $modelsCollection->toArray();
		}
		
		/**
		 * Returns the models from a relationship. Will always return as array.
		 *
		 * @param  Model $model
		 * @param  string $relationKey
		 *
		 * @return array|\Illuminate\Database\Eloquent\Collection
		 * @throws Exception
		 */
		public static function getModelsForRelation(Model $model, $relationKey) {
			if (method_exists($model, $relationKey) === false) {
				throw new Exception(
					[
						new Error (
							'Relation "' . $relationKey . '" does not exist in model',
							Error::RELATION_DOESNT_EXISTS_IN_MODEL,
							Response::HTTP_UNPROCESSABLE_ENTITY
						)
					]
				);
			}
			$relationModels = $model->{$relationKey};
			if (is_null($relationModels)) {
				return null;
			}
			
			if ( ! $relationModels instanceof Collection) {
				return [$relationModels];
			}
			
			return $relationModels;
		}
	}
