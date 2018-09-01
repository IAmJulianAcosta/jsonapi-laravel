<?php
/**
 * Class TopLevelObject
 *
 * @package IAmJulianAcosta\JsonApi\Data
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Data;

use IAmJulianAcosta\JsonApi\Exception;
use IAmJulianAcosta\JsonApi\Http\Response;
use Illuminate\Support\Collection;

class TopLevelObject extends ResponseObject {

  /**
   * @var Collection|TopLevelObject
   */
  protected $data;

  /**
   * @var array
   */
  protected $errors;

  /**
   * @var MetaObject
   */
  protected $meta;

  /**
   * @var array
   */
  protected $included;

  /**
   * @var array
   */
  protected $links;

  /**
   * TopLevelObject constructor.
   *
   * @param ResourceObject|Collection|null $data
   * @param Collection                     $errors
   * @param MetaObject|null                $meta
   * @param Collection|null                $included
   * @param LinksObject                    $links
   *
   * @throws Exception
   */
  public function __construct($data = null, $errors = null, MetaObject $meta = null, Collection $included = null,
    LinksObject $links = null
  ) {
    $this->data = $data;
    $this->errors = $errors;
    $this->meta = $meta;
    $this->included = $included;
    $this->links = $links;
    $this->validateRequiredParameters();
  }

  /**
   * @throws Exception
   */
  public function validateRequiredParameters() {
    $this->validatePresence($this->data, $this->errors, $this->meta);
    $this->validateCoexistence($this->data, $this->errors);
  }

  /**
   * @param $data
   * @param $errors
   * @param $meta
   *
   * @throws Exception
   */
  private function validatePresence($data, $errors, $meta) {
    if (empty ($data) && empty($errors) && empty ($meta)) {
      Exception::throwSingleException(
        "Either 'data', 'errors' or 'meta' object must be present on JSON API top level object",
        ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0
      );
    }
  }

  /**
   * @param $data
   * @param $errors
   *
   * @throws Exception
   */
  private function validateCoexistence($data, $errors) {
    if (!empty ($data) && !empty($errors)) {
      Exception::throwSingleException(
        "'data' and 'errors' object must be present on JSON API top level object",
        ErrorObject::LOGIC_ERROR, Response::HTTP_UNPROCESSABLE_ENTITY
      );
    }
  }

  /**
   * @return array|mixed
   * @throws Exception
   */
  public function jsonSerialize() {
    $returnArray = [
      "jsonapi" => [
        "version" => "1.0"
      ]
    ];

    $this->pushInstanceObjectToReturnArray($returnArray, "data");
    $this->pushInstanceObjectToReturnArray($returnArray, "errors");
    $this->pushInstanceObjectToReturnArray($returnArray, "meta");
    $this->pushInstanceObjectToReturnArray($returnArray, "included");
    $this->pushInstanceObjectToReturnArray($returnArray, "links");

    return $returnArray;
  }

  /**
   * @param $returnArray
   * @param $key
   *
   * @return mixed|void
   */
  protected function pushInstanceObjectToReturnArray(&$returnArray, $key) {
    parent::pushInstanceObjectToReturnArray($returnArray, $key);
  }

  public function isEmpty() {
    return empty ($this->data) && empty($this->errors) && ($this->meta);
  }

  /**
   * @return Collection|ResourceObject
   */
  public function getData() {
    return $this->data;
  }

  /**
   * @param Collection|ResourceObject $data
   *
   * @throws Exception
   */
  public function setData($data) {
    $this->data = $data;
    $this->validateRequiredParameters();
  }

  /**
   * @return array
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * @param array $errors
   *
   * @throws Exception
   */
  public function setErrors($errors) {
    $this->errors = $errors;
    $this->validateRequiredParameters();
  }

  /**
   * @return MetaObject
   */
  public function getMeta() {
    return $this->meta;
  }

  /**
   * @param MetaObject $meta
   *
   * @throws Exception
   */
  public function setMeta(MetaObject $meta) {
    $this->meta = $meta;
    $this->validateRequiredParameters();
  }

  /**
   * @return array
   */
  public function getIncluded() {
    return $this->included;
  }

  /**
   * @param array $included
   */
  public function setIncluded($included) {
    $this->included = $included;
  }

  /**
   * @return array
   */
  public function getLinks() {
    return $this->links;
  }

  /**
   * @param array $links
   */
  public function setLinks($links) {
    $this->links = $links;
  }
}
