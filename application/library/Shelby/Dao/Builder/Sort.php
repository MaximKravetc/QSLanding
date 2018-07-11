<?php
/**
 * Class
 */

namespace Shelby\Dao\Builder;

class Sort {

	private $sort = array();

	public function ascending(string $field) : Sort {
		$this->sort[$field] = 1;
		return $this;
	}

	public function descending(string $field) : Sort {
		$this->sort[$field] = -1;
		return $this;
	}

	/**
	 * Add old format sort specification
	 *
	 * @param array $sort
	 * @return Sort
	 */
	public function fromArray(array $sort) {
		foreach ($sort as $key => $value) {
			switch ($value) {
				case 'ASC':
				case 1:
					$this->sort[$key] = 1;
					break;
				case 'DESC':
				case -1:
					$this->sort[$key] = -1;
					break;
			}
		}

		return $this;
	}

	public function get() : array {
		return $this->sort;
	}

	public static function instance() : Sort {
		return new self();
	}

}