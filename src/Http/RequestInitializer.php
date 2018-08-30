<?php
/**
 * Class RequestInitializer
 *
 * @package IAmJulianAcosta\JsonApi\Http
 * @author  Julian Acosta <iam@julianacosta.me>
 */

namespace IAmJulianAcosta\JsonApi\Http;

use IAmJulianAcosta\JsonApi\Exception;
use Illuminate\Database\Eloquent\Collection;

class RequestInitializer {
  public static function initialize(Request &$request) {
    static::initializeInclude($request);
    static::initializeSort($request);
    static::initializeFilter($request);
    static::initializePage($request);
    static::getFieldsParametersFromRequest($request);
  }

  /**
   *
   */
  protected static function getFieldsParametersFromRequest(Request &$request) {
    $fieldsCollection = new Collection();
    $fields = $request->input('fields') ? $request->input('fields') : [];
    if (is_array($fields)) {
      foreach ($fields as $model => $field) {
        $fieldsCollection->put($model, array_filter(explode(',', $field)));
      }
    } else {
      Exception::throwSingleException('Fields parameter must be an array', 0, Response::HTTP_BAD_REQUEST);
    }
    $request->setFields($fieldsCollection);
  }

  /**
   *
   */
  protected static function initializeInclude(Request &$request) {
    $request->setInclude(($parameter = $request->input('include')) ? explode(',', $parameter) : []);
  }

  /**
   *
   */
  protected static function initializeSort(Request &$request) {
    $request->setSort(($parameter = $request->input('sort')) ? explode(',', $parameter) : []);
  }

  /**
   *
   */
  protected static function initializeFilter(Request &$request) {
    $request->setFilter(($parameter = $request->input('filter')) ? (is_array($parameter) ? $parameter : explode(',', $parameter)) : []);
  }

  /**
   *
   */
  protected static function initializePage(Request &$request) {
    $page = $request->input('page') ? $request->input('page') : [];

    if (!is_array($page)) {
      Exception::throwSingleException('Page parameter must be an array', 0, Response::HTTP_BAD_REQUEST);
    } else {
      static::checkIfPageIsValid($page);

      if (!empty($page)) {
        $request->setPage($page);
        $request->setPageSize((integer)$page['size']);
        $request->setPageNumber((integer)$page['number']);
      }
    }
  }

  /**
   * @param $page
   */
  protected static function checkIfPageIsValid($page) {
    if (!empty($page) && (empty($page['size']) || empty($page['number']))) {
      Exception::throwSingleException('Expected page[size] or page[number]', 0, Response::HTTP_BAD_REQUEST);
    }
  }
}
