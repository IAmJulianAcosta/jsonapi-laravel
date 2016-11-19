<?php
	/**
	 * Class QueryFilter
	 *
	 * @package EchoIt\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	/**
	 * Created by PhpStorm.
	 * User: julian-acosta
	 * Date: 29/09/16
	 * Time: 9:11 AM
	 */
	
	namespace EchoIt\JsonApi;
	
	use Illuminate\Support\Facades\DB;
	
	class QueryFilter {
		
		static private $methodsThatReceiveAnArray = ["whereBetween", "whereNotBetween", "whereIn", "whereNotIn"];
		/**
		 * Function to handle sorting requests.
		 */
		public static function sortRequest (Request $request, Builder &$query) {
			$sort = $request->originalRequest->input('sort');
			$explodedSort = explode(",", $sort);
			foreach ($explodedSort as $parameter) {
				$isDesc = starts_with ($parameter, "-");
				$direction = $isDesc ? 'desc' : 'asc';
				if ($isDesc) {
					$parameter = substr($parameter, 1);
				}
				$query->orderBy($parameter, $direction);
			}
		}
		
		
		/**
		 * Filters the request by [filter] parameters in request
		 *
		 * @param Request $request
		 * @param         $tableName
		 */
		static public function filterRequest(Request $request, $tableName, $query = null) {
			/*
			 * ?filter[foo]=where,bar&filter[people]=whereBetween,1,100&filter[bar]=where[(orWhere,body),(orWhere,foo)]
			 *
			 * This request is telling me that I need to filter the request by foo column, where foo is body
			 *
			 * Also is telling me that I need to filter the request by people, with parameter name
			 *
			 */
			if (is_null($query)) {
				$query = DB::table ($tableName);
			}
			$filters = $request->originalRequest->input('filter');
			static::applyFilters($filters, $query);
		}
		
		
		/**
		 * Will receive the query and apply the filters defined in GET parameters
		 *
		 * @param $filters
		 * @param $query
		 */
		static private function applyFilters ($filters, &$query) {
			foreach ($filters as $filterName => $filterValues) {
				static::parse($filterValues, $filterName, $query);
			}
		}
		
		/**
		 * @param             $filterValues
		 * @param             $query
		 */
		static private function parseGroup($filterValues, &$query) {
			//This regex matches the method: method[...] and removes it from array https://regex101.com/r/ml0o88/4
			$mainRegex = '/([a-zA-Z]*)\[((?:\([a-z]+=(?:[0-9a-zA-Z,=<>\[\]\(\)]+,?)+\),?)+)\]/';
			preg_match_all($mainRegex, $filterValues, $matches);
			$method = $matches [1][0];
			if (empty($matches[2]) === false) {
				//This regex separates the group methods https://regex101.com/r/pLeQCq/4
				$parenthesesRegex = '/\((?:(?:[a-z]+=(?:[[:alnum:]<>=]+,?)+)|(?:[a-z]+=\[?[a-zA-Z0-9,=<>\[\]\(\)]*?\]))\)/';
				preg_match_all($parenthesesRegex, $matches[2][0], $parenthesesMatches);
				$parenthesesMatches = $parenthesesMatches [0];
				//The callback will use the separated methods
				$callback = function ($query) use ($parenthesesMatches) {
					//Add the methods to query
					foreach ($parenthesesMatches as $parenthesesMatch) {
						//This regex will explode the string with method and parameters https://regex101.com/r/9cZcvM/4
						$methodRegex = '/\(([a-z]+)=((?:[0-9a-zA-Z,=<>\[\]\(\)]+,?)+)\)/';
						preg_match_all($methodRegex, $parenthesesMatch, $methodMatches);
						
						$filterName   = $methodMatches[1][0];
						$filterValues = $methodMatches[2][0];
						
						//This will parse the result again, calling the correct method
						QueryFilter::parse($filterValues, $filterName, $query);
					}
				};
				call_user_func(array ($query, $method), $callback);
			}
		}
		
		/**
		 * @param $filterValues
		 * @param $filterName
		 * @param $query
		 */
		static private function parseMethod($filterValues, $filterName, &$query) {
			//First explode the comma separated string into array
			$filterValuesArray = explode(",", $filterValues);
			
			//The method is the first parameter, so remove it from array
			$method = array_shift($filterValuesArray);
			
			//Add as first parameter the column that is queried
			array_unshift($filterValuesArray, $filterName);
			
			//If the method requires the second parameter as an array, create a new array with first parameter as
			//string and second parameter as array
			if (in_array($method, static::$methodsThatReceiveAnArray) === true) {
				$filterValuesArray = [array_shift($filterValuesArray), $filterValuesArray];
			}
			
			/*At this moment we have an array with the first parameter with the column, and the other ones with the query
			operators, something like ['votes', '>=', 100]. The method name is out of the array and stored in $method.
	
			So the next step is calling the method in $query object, passing the array.
			*/
			$query = call_user_func_array(array ($query, $method), $filterValuesArray);
		}
		
		/**
		 * @param $filterValues
		 * @param $filterName
		 * @param $query
		 */
		static private function parse($filterValues, $filterName, &$query) {
			/*The first step if checking if filter name is "group", If is the case, split them and treat this as
			a single query, if not, parse the string and group the methods:  */
			if ($filterName === "group") {
				static::parseGroup($filterValues, $query);
			} else {
				static::parseMethod($filterValues, $filterName, $query);
			}
		}
	}
