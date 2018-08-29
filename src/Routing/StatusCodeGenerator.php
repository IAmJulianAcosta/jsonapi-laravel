<?php
	/**
	 * Class StatusCodeGenerator
	 *
	 * @package IAmJulianAcosta\JsonApi\Routing
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Routing;
	
	use IAmJulianAcosta\JsonApi\Data\ErrorObject;
	use IAmJulianAcosta\JsonApi\Data\TopLevelObject;
	use IAmJulianAcosta\JsonApi\Database\Eloquent\Model;
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\Response;
	
	class StatusCodeGenerator {
        /**
         * A method for getting the proper HTTP status code for a successful request
         *
         * @param  string $method "PUT", "POST", "DELETE" or "GET"
         * @param  Model|Collection|LengthAwarePaginator|null $model The model that a PUT request was executed against
         * @return int
         * @throws Exception
         */
		public static function successfulHttpStatusCode($method, TopLevelObject $topLevelObject, $model = null) {
			// if we did a put request, we need to ensure that the model wasn't
			// changed in other ways than those specified by the request
			//     Ref: http://jsonapi.org/format/#crud-updating-responses-200
			
			switch ($method) {
				case 'POST':
					return static::generateCodeForPostRequest();
				case 'PATCH':
				case 'PUT':
					return static::generateCodeForPatchRequest($model);
				case 'DELETE':
					return static::generateCodeForDeleteRequest($topLevelObject);
				case 'GET':
					return static::generateCodeForGetRequest();
			}
			
			// Code shouldn't reach this point, but if it does we assume that the
			// client has made a bad request.
			return Response::HTTP_BAD_REQUEST;
		}
		
		protected static function generateCodeForGetRequest() {
			return Response::HTTP_OK;
		}
		
		/**
		 * @return mixed
		 */
		protected static function generateCodeForPostRequest() {
			return Response::HTTP_CREATED;
		}
		
		/**
		 * @param Model $model
		 *
		 * @return int
		 * @throws Exception
		 */
		protected static function generateCodeForPatchRequest(Model $model) {
			if (is_null($model) === false && $model->isChanged() === true) {
				return Response::HTTP_OK;
			}
			Exception::throwSingleException(
				'An unknown error occurred', ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR
			);
		}
		
		protected static function generateCodeForDeleteRequest(TopLevelObject $topLevelObject) {
			if (empty($topLevelObject->getMeta()) === true) {
				return Response::HTTP_NO_CONTENT;
			}
			else {
				return Response::HTTP_OK;
			}
		}
	}
