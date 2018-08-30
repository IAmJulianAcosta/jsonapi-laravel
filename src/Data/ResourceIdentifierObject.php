<?php
/**
 * Class ResourceIdentifierObject
 *
 * @package IAmJulianAcosta\JsonApi\Data
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Data;

use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
use IAmJulianAcosta\JsonApi\Exception;
use IAmJulianAcosta\JsonApi\Http\Response;

class ResourceIdentifierObject extends ResponseObject {
  /**
   * @var Model $model
   */
  protected $model;

  /**
   * @var integer
   */
  protected $id;

  /**
   * @var string
   */
  protected $type;

  public function __construct(Model $model) {
    //First remove all unwanted keys
    $model->filterForeignKeys();
    $this->model = $model;
    $this->setParameters();
    $this->validateRequiredParameters();
  }

  /**
   * Convert this model to an array with the JSON Api structure
   */
  protected function setParameters() {
    $model = $this->model;

    $key = $model->getKey();
    $type = $model->getResourceType();

    $this->id = $key;
    $this->type = $type;
  }

  /**
   * Validates if required parameters for object are valid.
   */
  public function validateRequiredParameters() {
    if (empty ($this->type) === true) {
      Exception::throwSingleException("Model type invalid", 0, Response::HTTP_INTERNAL_SERVER_ERROR, 0);
    }
    if (empty ($this->id) === true) {
      Exception::throwSingleException("Model ID invalid", 0, Response::HTTP_INTERNAL_SERVER_ERROR, 0);
    }
  }

  public function jsonSerialize() {
    $returnArray = [];

    $this->pushInstanceObjectToReturnArray($returnArray, "id");
    $this->pushInstanceObjectToReturnArray($returnArray, "type");

    return $returnArray;
  }

  public function isEmpty() {
    return empty ($this->id) || empty($this->type);
  }

  /**
   * @return Model
   */
  public function getModel() {
    return $this->model;
  }

  /**
   * @param Model $model
   */
  public function setModel($model) {
    $this->model = $model;
  }

  /**
   * @return int
   */
  public function getId() {
    return $this->id;
  }

  /**
   * @param int $id
   */
  public function setId($id) {
    $this->id = $id;
  }

  /**
   * @return string
   */
  public function getType() {
    return $this->type;
  }

  /**
   * @param string $type
   */
  public function setType($type) {
    $this->type = $type;
  }

}
