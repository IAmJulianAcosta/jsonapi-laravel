<?php namespace EchoIt\JsonApi;

use Illuminate\Http\Request as BaseRequest;

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
     * Specifies the page number to return results for
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
	
	public function __construct(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null) {
		parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
		$this->initializeVariables();
	}
	
	protected function initializeVariables () {
		$this->include    = ($parameter = $this->input('include')) ? explode(',', $parameter) : [];
		$this->sort       = ($parameter = $this->input('sort')) ? explode(',', $parameter) : [];
		$this->filter     = ($parameter = $this->input('filter')) ? $parameter : [];
		$this->page       = $this->input('page') ? $this->input('page') : [];
		$this->pageSize   = null;
		$this->pageNumber = null;
		
		if (isset ($this->page) === true && empty($this->page) === false) {
			if (is_array($this->page) === true && empty($this->page['size']) === false && empty($this->page['number']) === false) {
				$this->pageSize   = $this->page['size'];
				$this->pageNumber = $this->page['number'];
			}
			else {
				//throw error
				//						return new ApiErrorResponse(400, 400, 'Expected page[size] and page[number]');
			}
		}
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
}
