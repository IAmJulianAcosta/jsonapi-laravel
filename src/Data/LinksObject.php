<?php
	/**
	 * Class LinksObject
	 *
	 * @package IAmJulianAcosta\JsonApi\Data
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace IAmJulianAcosta\JsonApi\Data;
	
	use IAmJulianAcosta\JsonApi\Exception;
	use IAmJulianAcosta\JsonApi\Http\Response;
	use Illuminate\Support\Collection;
	
	class LinksObject extends ResponseObject {
		
		/**
		 * @var Collection
		 */
		protected $links;
		
		public function __construct(Collection $links) {
			$this->setLinks($links);
		}
		
		public function validateRequiredParameters() {
			foreach ($this->links as $link) {
				if ($link instanceof LinkObject === false) {
					Exception::throwSingleException("Links object can only contain LinkObjects",
						ErrorObject::UNKNOWN_ERROR, Response::HTTP_INTERNAL_SERVER_ERROR, 0);
				}
			}
		}
		
		public function jsonSerialize() {
			return $this->links;
		}
		
		public function isEmpty() {
			return $this->links->isEmpty();
		}
		
		/**
		 * @return Collection
		 */
		public function getLinks() {
			return $this->links;
		}
		
		/**
		 * @param Collection $links
		 */
		public function setLinks(Collection $links) {
			$this->links = $links->mapWithKeys(
				function (LinkObject $link) {
					return [$link->getKey() => $link];
				}
			);
			$this->validateRequiredParameters();
		}
		
		public function addLink (LinkObject $link) {
			$this->links->push($link);
		}
		
		public function addLinks (Collection $links) {
			$this->links->merge($links);
		}
		
	}
