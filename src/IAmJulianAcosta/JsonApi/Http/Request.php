<?php
	namespace IAmJulianAcosta\JsonApi\Http;
	
	use IAmJulianAcosta\JsonApi\Data\RequestObject;
	use IAmJulianAcosta\JsonApi\Exception;
	use Illuminate\Http\Request as BaseRequest;
	use Illuminate\Support\Collection;
	
	/**
	 * A class used to represented a client request to the API.
	 *
	 * @author Julián Acosta <julian.acosta@mandarinazul.co>
	 * @author Ronni Egeriis Persson <ronni@egeriis.me>
	 */
	class Request extends BaseRequest {
		
		/**
		 * Contains the url of the request
		 *
		 * @var string
		 */
		protected $url;
		
		/**
		 * Contains an optional model ID from the request
		 *
		 * @var int
		 */
		protected $id;
		
		/**
		 * Contains an array of linked resource collections to load
		 *
		 * @var array
		 */
		protected $include;
		
		/**
		 * Contains an array of fields to load
		 *
		 * @var Collection
		 */
		protected $fields;
		
		/**
		 * Contains an array of column names to sort on
		 *
		 * @var array
		 */
		protected $sort;
		
		/**
		 * Contains an array of key/value pairs to filter on
		 *
		 * @var array
		 */
		protected $filter;
		
		/**
		 * @var array
		 */
		protected $page;
		
		/**
		 * Specifies the page number to return results for
		 *
		 * @var integer
		 */
		protected $pageNumber;
		
		/**
		 * Specifies the number of results to return per page. Only used if
		 * pagination is requested (ie. pageNumber is not null)
		 *
		 * @var integer
		 */
		protected $pageSize = 50;
		
		/**
		 * @var string Defines the guard type used by this request
		 */
		protected $guardType;
		
		/**
		 * @var bool Is this request is an auth requeest.
		 */
		protected $isAuthRequest;
		
		/**
		 * @var RequestObject
		 */
		protected $jsonApiContent;
		
		public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [],
			array $files = [], array $server = [], $content = null
		) {
			parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
		}
		
		/**
		 * Converts an illuminate typed request to a JSON API request, throws exception if is not a JSON API request
		 *
		 * @param BaseRequest $request
		 *
		 * @return Request
		 * @throws \LogicException
		 */
		public static function convertIlluminateRequestToJsonApiRequest(BaseRequest $request) {
			if ($request instanceof \IAmJulianAcosta\JsonApi\Http\Request) {
				return $request;
			} else {
				throw new \LogicException("You must configure your laravel installation to use JSON API request");
			}
		}
		
		public function duplicate(
			array $query = null, array $request = null, array $attributes = null, array $cookies = null,
			array $files = null, array $server = null
		) {
			/** @var Request $duplicated */
			$duplicated = parent::duplicate($query, $request, $attributes, $cookies, $this->filterFiles($files),
				$server);
			$duplicated->initializeVariables();
			$duplicated->checkRequestContentType();
			$duplicated->checkRequestAccept();
			return $duplicated;
		}
		
		public function getContentTypeMediaTypes () {
			//Get the content type header
			$contentTypeHeader = $this->headers->get('CONTENT_TYPE');
			//Convert to array
			$contentTypeHeader = explode (';', $contentTypeHeader);
			//Remove first element
			array_shift($contentTypeHeader);
			
			return $contentTypeHeader;
		}

		protected function initializeVariables() {
			$this->initializeInclude();
			$this->initializeSort();
			$this->initializeFilter();
			$this->initializePage();
			$this->getFieldsParametersFromRequest();
		}
		
		protected function getFieldsParametersFromRequest () {
			$this->fields = new Collection();
			foreach ($this->input('fields') as $model => $fields) {
				$this->fields->put($model, array_filter(explode(',', $fields)));
			}
		}
		
		protected static function initializeFormats() {
			static::$formats = array(
				'html' => array('text/html', 'application/xhtml+xml'),
				'txt' => array('text/plain'),
				'js' => array('application/javascript', 'application/x-javascript', 'text/javascript'),
				'css' => array('text/css'),
				'json' => array('application/json', 'application/x-json'),
				'xml' => array('text/xml', 'application/xml', 'application/x-xml'),
				'rdf' => array('application/rdf+xml'),
				'atom' => array('application/atom+xml'),
				'rss' => array('application/rss+xml'),
				'form' => array('application/x-www-form-urlencoded'),
				'jsonapi' => array('application/vnd.api+json')
			);
		}
		
		protected function checkRequestContentType () {
			if ($this->getContentType() === "jsonapi" || true) {
				$mediaTypes = $this->getContentTypeMediaTypes();
				
				if (empty($mediaTypes) === false) {
					Exception::throwSingleException(
						"Content-Type header can't have media type parameters", 0, Response::HTTP_NOT_ACCEPTABLE
					);
				}
			}
			else {
				Exception::throwSingleException(
					"Content-Type header must be application/vnd.api+json", 0, Response::HTTP_NOT_ACCEPTABLE
				);
			}
		}
		
		protected function checkRequestAccept () {
			$acceptHeaders = $this->header("accept");
			if (empty($acceptHeaders) === false) {
				$acceptHeaders = explode (';', $acceptHeaders);
				if (count($acceptHeaders) > 0 && $acceptHeaders [0] === "application/vnd.api+json" &&
				    count($acceptHeaders) > 1) {
					Exception::throwSingleException("Accept type can't have media type parameters",
						0, Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
				}
			}
		}
		
		/**
		 * Parses content from request into an array of values.
		 *
		 * @throws \IAmJulianAcosta\JsonApi\Exception
		 */
		public function extractData () {
			if ($this->shouldHaveContent() === true) {
				$content = json_decode($this->getContent(), true);
				
				$this->jsonApiContent = new RequestObject($content, $this);
			}
		}
		
		/**
		 * @return RequestObject
		 */
		public function getJsonApiContent() {
			return $this->jsonApiContent;
		}
		
		/**
		 * @param RequestObject $jsonApiContent
		 */
		public function setJsonApiContent($jsonApiContent) {
			$this->jsonApiContent = $jsonApiContent;
		}
		
		public function getData () {
			return $this->jsonApiContent->getData ();
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
		 * @return array
		 */
		public function getFilter() {
			return $this->filter;
		}
		
		/**
		 * @param array $filter
		 */
		public function setFilter($filter) {
			$this->filter = $filter;
		}
		
		/**
		 * @return array
		 */
		public function getInclude() {
			return $this->include;
		}
		
		/**
		 * @param array $include
		 */
		public function setInclude($include) {
			$this->include = $include;
		}
		
		/**
		 * @return int
		 */
		public function getPageNumber() {
			return $this->pageNumber;
		}
		
		/**
		 * @param int $pageNumber
		 */
		public function setPageNumber($pageNumber) {
			$this->pageNumber = $pageNumber;
		}
		
		/**
		 * @return int
		 */
		public function getPageSize() {
			return $this->pageSize;
		}
		
		/**
		 * @param int $pageSize
		 */
		public function setPageSize($pageSize) {
			$this->pageSize = $pageSize;
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
		public function getSort() {
			return $this->sort;
		}
		
		/**
		 * @param array $sort
		 */
		public function setSort($sort) {
			$this->sort = $sort;
		}
		
		/**
		 * @return string
		 */
		public function getGuardType() {
			return $this->guardType;
		}
		
		/**
		 * @param string $guardType
		 */
		public function setGuardType($guardType) {
			$this->guardType = $guardType;
		}
		
		/**
		 * @return array
		 */
		public function getPage() {
			return $this->page;
		}
		
		/**
		 * @param array $page
		 */
		public function setPage($page) {
			$this->page = $page;
		}
		
		/**
		 * @return Collection
		 */
		public function getFields() {
			return $this->fields;
		}
		
		/**
		 * @param Collection $fields
		 */
		public function setFields($fields) {
			$this->fields = $fields;
		}
		
		/**
		 * @return bool
		 */
		public function shouldHaveContent() {
			return $this->getMethod() === "PATCH" || $this->getMethod() === "PUT" || $this->getMethod() === "POST";
		}
		
		public function setAuthRequest ($isAuthRequest) {
			$this->isAuthRequest = $isAuthRequest;
		}
		
		public function isAuthRequest() {
			return $this->isAuthRequest;
		}
		
		/**
		 *
		 */
		protected function initializeInclude() {
			$this->include = ($parameter = $this->input('include')) ? explode(',', $parameter) : [];
		}
		
		/**
		 *
		 */
		protected function initializeSort() {
			$this->sort = ($parameter = $this->input('sort')) ? explode(',', $parameter) : [];
			
		}
		
		protected function initializeFilter() {
			$this->filter = ($parameter = $this->input('filter')) ? (is_array($parameter) ? $parameter : explode(',',
				$parameter)) : [];
		}
		
		/**
		 *
		 */
		protected function initializePage() {
			$this->page       = $page = $this->input('page') ? $this->input('page') : [];
			
			$this->checkIfPageIsValid($page);
			
			$this->pageSize   = (integer)$page['size'];
			$this->pageNumber = (integer)$page['number'];
		}
		
		/**
		 * @param $page
		 */
		protected function checkIfPageIsValid($page) {
			if (is_array($page) === false || empty($page['size']) === true || empty($page['number']) === true) {
				Exception::throwSingleException('Expected page[size] and page[number]', 0, Response::HTTP_BAD_REQUEST);
			}
		}
		
	}
