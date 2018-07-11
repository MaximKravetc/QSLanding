<?php
/**
 * Class Shelby_Dao_Builder_Search
 */

namespace Shelby\Dao\Builder;

class Search {

	private $extraParams = array();

	public function equals(string $field, $value, bool $prepare = true) : Search {
		$this->extraParams[] = array('field' => $field, 'value' => $value, 'type' => '=', 'prepare' => $prepare);
		return $this;
	}

	public function in(string $field, array $value, bool $prepare = true) : Search {
		$this->extraParams[] = array('field' => $field, 'value' => $value, 'type' => 'in', 'prepare' => $prepare);
		return $this;
	}

	public function greater(string $field, $value, bool $prepare = true) : Search {
		$this->extraParams[] = array('field' => $field, 'value' => $value, 'type' => '>', 'prepare' => $prepare);
		return $this;
	}

	public function less(string $field, $value, bool $prepare = true) : Search {
		$this->extraParams[] = array('field' => $field, 'value' => $value, 'type' => '<', 'prepare' => $prepare);
		return $this;
	}

	public function greaterOrEqual(string $field, $value, bool $prepare = true) : Search {
		$this->extraParams[] = array('field' => $field, 'value' => $value, 'type' => '>=', 'prepare' => $prepare);
		return $this;
	}

	public function lessOrEqual(string $field, $value, bool $prepare = true) : Search {
		$this->extraParams[] = array('field' => $field, 'value' => $value, 'type' => '<=', 'prepare' => $prepare);
		return $this;
	}

	public function notEqual(string $field, $value, bool $prepare = true) : Search {
		$this->extraParams[] = array('field' => $field, 'value' => $value, 'type' => '!=', 'prepare' => $prepare);
		return $this;
	}

	public function startsWith(string $field, $value, bool $prepare = true) : Search {
		$this->extraParams[] = array('field' => $field, 'value' => $value, 'type' => '~%', 'prepare' => $prepare);
		return $this;
	}

	/**
	 * Case insensitive field search
	 * Can not use index, very slow on large data sets
	 *
	 * @param string $field
	 * @param mixed $value
	 * @param bool $prepare
	 * @return Search
	 */
	public function like(string $field, $value, bool $prepare = true) : Search {
		$this->extraParams[] = array('field' => $field, 'value' => $value, 'type' => '~', 'prepare' => $prepare);
		return $this;
	}

	/**
	 * Custom MongoDB find query
	 *
	 * @param array $value
	 * @return Search
	 */
	public function custom(array $value) : Search {
		$this->extraParams[] = array('field' => '_custom_', 'value' => $value, 'type' => 'custom', 'prepare' => false);
		return $this;
	}

	/**
	 * Add old format search filter
	 *
	 * @param array $search
	 * @return Search
	 */
	public function fromArray(array $search) {
		$this->extraParams = array_merge($this->extraParams, $search);
		return $this;
	}

	public function get() : array {
		return $this->extraParams;
	}

	public static function instance() : Search {
		return new self();
	}

}