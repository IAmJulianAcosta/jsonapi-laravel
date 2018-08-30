<?php

namespace IAmJulianAcosta\JsonApi\Routing;

use IAmJulianAcosta\JsonApi\Data\ErrorObject;
use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
use IAmJulianAcosta\JsonApi\Exception;
use IAmJulianAcosta\JsonApi\Http\Response;
use IAmJulianAcosta\JsonApi\SqlError;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

abstract class DataModifierHandler extends Handler {
  /**
   * @param $model
   *
   * @throws Exception
   */
  protected function saveModel(Model $model) {
    try {
      $model->saveOrFail();
    } catch (QueryException $exception) {
      throw new Exception(
        new Collection(
          new SqlError ('Database error', ErrorObject::DATABASE_ERROR,
            Response::HTTP_INTERNAL_SERVER_ERROR, $exception)
        )
      );
    } catch (\Exception $exception) {
      Exception::throwSingleException(
        'An unknown error occurred saving the record', ErrorObject::UNKNOWN_ERROR,
        Response::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }

  /**
   * @param $modelName
   *
   * @return Model
   * @throws Exception
   */
  protected function tryToFindModel($modelName) {
    try {
      $id = $this->request->getId();
      $this->validateIfIdIsPresentInRequest($id);
      /** @var Model $model */
      $model = $modelName::findOrFail($id);

      if (is_null($model) === true) {
        throw new ModelNotFoundException();
      }

      return $model;
    } catch (ModelNotFoundException $e) {
      $title = 'Record not found in Database';
      $code = ErrorObject::UNKNOWN_ERROR;
      $status = Response::HTTP_NOT_FOUND;
      $resourceIdentifier = static::ERROR_SCOPE;
      Exception::throwSingleException($title, $code, $status, $resourceIdentifier);
    }
    return null;
  }

  protected function validateIfIdIsPresentInRequest($id) {
    if (empty($id) === true) {
      Exception::throwSingleException(
        'No ID provided', ErrorObject::NO_ID, Response::HTTP_BAD_REQUEST, static::ERROR_SCOPE
      );
    }
  }
}
