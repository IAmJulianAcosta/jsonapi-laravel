<?php
/**
 * Class MetaObject
 *
 * @package IAmJulianAcosta\JsonApi\Data
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Data;

use IAmJulianAcosta\JsonApi\Exception;
use IAmJulianAcosta\JsonApi\Http\Response;
use Illuminate\Support\Collection;

class MetaObject extends ResponseObject {

  /**
   * @var Collection
   */
  protected $metaObjects;

  public function __construct(Collection $metaObjects) {
    $this->metaObjects = $metaObjects;
  }

  /**
   * @throws Exception
   */
  public function validateRequiredParameters() {
    if ($this->metaObjects->isEmpty()) {
      Exception::throwSingleException("Meta object should not be empty",
        ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0);
    }
  }

  public function jsonSerialize() {
    $returnArray = [];

    foreach ($this->metaObjects as $key => $metaObject) {
      $this->pushToReturnArray($returnArray, $key, $metaObject);
    }

    return $returnArray;
  }

  public function isEmpty() {
    return $this->metaObjects->isEmpty();
  }
}
