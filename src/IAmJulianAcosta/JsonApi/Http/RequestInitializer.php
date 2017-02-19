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
		public static function initialize (Request &$request) {
			static::initializeInclude($request);
			static::initializeSort($request);
			static::initializeFilter($request);
			static::initializePage($request);
			static::getFieldsParametersFromRequest($request);
		}
		
		/**
		 *
		 */
		static protected function getFieldsParametersFromRequest (Request &$request) {
			$fieldsCollection = new Collection();
			foreach ($request->input('fields') as $model => $fields) {
				$fieldsCollection->put($model, array_filter(explode(',', $fields)));
			}
			$request->setFields($fieldsCollection);
		}
		
		/**
		 *
		 */
		static protected function initializeInclude(Request &$request) {
			$request->setInclude(($parameter = $request->input('include')) ? explode(',', $parameter) : []);
		}
		
		/**
		 *
		 */
		static protected function initializeSort(Request &$request) {
			$request->setSort(($parameter = $request->input('sort')) ? explode(',', $parameter) : []);
		}
		
		/**
		 *
		 */
		static protected function initializeFilter(Request &$request) {
			$request->setFilter(($parameter = $request->input('filter')) ? (is_array($parameter) ? $parameter : explode(',', $parameter)) : []);
		}
		
		/**
		 *
		 */
		static protected function initializePage(Request &$request) {
			$page = $request->input('page') ? $request->input('page') : [];
			
			static::checkIfPageIsValid($page);
			
			$request->setPage($page);
			$request->setPageSize((integer)$page['size']);
			$request->setPageNumber((integer)$page['number']);
		}
		
		/**
		 * @param $page
		 */
		static protected function checkIfPageIsValid($page) {
			if (is_array($page) === false || empty($page['size']) === true || empty($page['number']) === true) {
				Exception::throwSingleException('Expected page[size] and page[number]', 0, Response::HTTP_BAD_REQUEST);
			}
		}
	}
