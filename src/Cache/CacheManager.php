<?php
/**
 * Class CacheManager
 *
 * @package IAmJulianAcosta\JsonApi
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Cache;

use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
use Illuminate\Support\Pluralizer;
use Cache;

class CacheManager {

  /**
   * @param $resourceName
   *
   * @return string
   */
  public static function getQueryCacheForMultipleResources($resourceName) {
    return Pluralizer::plural($resourceName) . ":query";
  }

  /**
   * @param $id
   * @param $resourceName
   *
   * @return string
   */
  public static function getQueryCacheForSingleResource($id, $resourceName) {
    return $resourceName . ":query:" . $id;
  }

  /**
   * @param $resourceName
   *
   * @return string
   */
  public static function getResponseCacheForMultipleResources($resourceName) {
    return Pluralizer::plural($resourceName) . ":response";
  }

  /**
   * @param $id
   * @param $resourceName
   *
   * @return string
   */
  public static function getResponseCacheForSingleResource($id, $resourceName) {
    return $resourceName . ":response:" . $id;
  }

  /**
   * @param $resourceType
   * @param $key
   *
   * @return string
   */
  public static function getArrayCacheKeyForSingleResource($resourceType, $key) {
    return $resourceType . ":array:" . $key . ":relations";
  }

  public static function getArrayCacheKeyForSingleResourceWithoutRelations($resourceType, $key) {
    return $resourceType . ":array:" . $key . ":no_relations";
  }


  /**
   * @param                                                  $resourceName
   * @param null                                             $id
   * @param \IAmJulianAcosta\JsonApi\Database\Eloquent\Model $model
   *
   * @throws \ReflectionException
   */
  public static function clearCache($resourceName, $id = null, Model $model = null) {
    //ID passed = update record
    if ($id !== null && $model !== null) {
      $key = static::getQueryCacheForSingleResource($id, $resourceName);
      Cache::forget($key);
      $key = static::getResponseCacheForSingleResource($id, $resourceName);
      Cache::forget($key);
      $key = static::getArrayCacheKeyForSingleResource($model->getResourceType(), $model->getKey());
      Cache::forget($key);
      $key = static::getArrayCacheKeyForSingleResourceWithoutRelations($model->getResourceType(),
        $model->getKey());
      Cache::forget($key);
    }
    $key = static::getQueryCacheForMultipleResources($resourceName);
    Cache::forget($key);
    $key = static::getResponseCacheForMultipleResources($resourceName);
    Cache::forget($key);
  }
}
