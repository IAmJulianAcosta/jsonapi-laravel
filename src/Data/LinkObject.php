<?php
/**
 * Class LinkObject
 *
 * @package IAmJulianAcosta\JsonApi\Data
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Data;

use IAmJulianAcosta\JsonApi\Exception;
use IAmJulianAcosta\JsonApi\Http\Response;

class LinkObject extends ResponseObject {

  /**
   * @var string
   */
  protected $url;

  /**
   * @var array
   */
  protected $meta;

  /**
   * @var array
   */
  protected $linkObject;

  /**
   * @var string
   */
  protected $key;

  public function __construct($key, $url, $meta = []) {
    $this->url = $url;
    $this->meta = $meta;
    $this->key = $key;

    //No meta, just the URL
    if (empty($meta) === false) {
      $this->linkObject = [
        "href" => $url,
        "meta" => $meta
      ];
    } else {
      $this->url = $url;
    }
    $this->validateRequiredParameters();
  }

  public function validateRequiredParameters() {
    if (empty ($this->key) === true) {
      Exception::throwSingleException("Key must be present on link object",
        ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0);
    }
    if (empty ($this->url) === true && empty ($this->meta) === true) {
      Exception::throwSingleException("Url or meta object must be present on link object",
        ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0);
    }
  }

  public function jsonSerialize() {
    $returnArray = [];
    if (empty($this->linkObject) === false) {
      $this->pushInstanceObjectToReturnArray($returnArray, "related");
      return $returnArray;
    } else {
      return $this->url;
    }
  }

  public function isEmpty() {
    return empty ($this->linkObject) && empty ($this->url);
  }

  /**
   * @return string
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * @param string $url
   */
  public function setUrl($url) {
    $this->url = $url;
  }

  /**
   * @return array
   */
  public function getMeta() {
    return $this->meta;
  }

  /**
   * @param array $meta
   */
  public function setMeta($meta) {
    $this->meta = $meta;
  }

  /**
   * @return array
   */
  public function getLinkObject() {
    return $this->linkObject;
  }

  /**
   * @param array $linkObject
   */
  public function setLinkObject($linkObject) {
    $this->linkObject = $linkObject;
  }

  /**
   * @return string
   */
  public function getKey() {
    return $this->key;
  }

  /**
   * @param string $key
   */
  public function setKey($key) {
    $this->key = $key;
  }

}
