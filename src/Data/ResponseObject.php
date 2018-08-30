<?php
/**
 * Class ResponseObject
 *
 * @package IAmJulianAcosta\JsonApi\Data
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use JsonSerializable;

abstract class ResponseObject extends JSONAPIDataObject implements JsonSerializable {
  /**
   * Adds an object property to array if not empty
   *
   * @param $returnArray
   * @param $key
   *
   * @see Collection::jsonSerialize()
   */
  protected function pushToReturnArray(&$returnArray, $key, $object) {
    if ($this->checkEmpty($object) === false) {
      if ($object instanceof JsonSerializable) {
        $returnArray [$key] = $object->jsonSerialize();

        return $returnArray;
      } else if ($object instanceof Jsonable) {
        $returnArray [$key] = json_decode($object->toJson(), true);

        return $returnArray;
      } else if ($object instanceof Arrayable) {
        $returnArray [$key] = $object->toArray();

        return $returnArray;
      } else {
        $returnArray [$key] = $object;

        return $returnArray;
      }
    }

    return $returnArray;
  }

  /**
   * @param $returnArray
   * @param $key
   *
   * @return mixed
   */
  protected function pushInstanceObjectToReturnArray(&$returnArray, $key) {
    $this->pushToReturnArray($returnArray, $key, $this->{$key});

    return $returnArray;
  }

  /**
   * @param JSONAPIDataObject|mixed $object
   *
   * @return bool
   */
  protected function checkEmpty($object) {
    if ($object instanceof ResponseObject) {
      return $object->isEmpty();
    } else if ($object instanceof Collection) {
      return $object->isEmpty();
    } else {
      return empty($object);
    }
  }

  /**
   * @return mixed
   */
  public abstract function isEmpty();

  public abstract function validateRequiredParameters();
}
