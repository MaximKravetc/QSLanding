<?PHP
class Dao_Fulltext_Synonyms extends Dao_Abstract {

	public function __construct() {
		$lower = new Zend_Filter_StringToLower();
		$lower->setEncoding('UTF-8');

		$this->_filterChainPlain = new Zend_Filter();

		$this->_filterChainPlain->addFilter($lower);
	}

	/**
	 * Return synonyms list for the word
	 *
	 * @param string $word
	 * @return array
	 */
	public function getSynonymsList($word) {
		$word = trim($this->_filterChainPlain->filter($word));

		$res = Dao_Mongodb_List_Sbd_Synonyms::getInstance()->getList(1, null, array(
			array('field' => 'words', 'value' => $word, 'type' => '=')
		))->get();

		if (empty($res)) {
			return array();
		}

		return $res[0]['words'];
	}

	/**
	 * Производить синонимизацию текста, заменяя слова и словосочетания их синонимами.
	 *
	 * TODO: Текущий алгоритм может привести к искажению смысла текста из-за того, что
	 * допускает замену единичных слов, входящих в словосоченияния. Чтобы это исправить
	 * нужно проходить по словосочетаниям в порядке уменьшения содержащихся в них слов,
	 * при замене все зависимые элементы очищаются
	 *
	 * @param string $data
	 * @return array
	 */
	public function synonymize($data) {
		$result = array('stats' => array(), 'data' => &$data);

		/*Сперва необходимо подготовить текст к разбору - заменить пробелами основные
		знаки препинания и и некоторые специальные символы. Так же нужно убрать возможные
		пробелы в конце чтобы не усложнять функцию разбора текста на слова*/
		$data_ch = rtrim(str_replace(array('.', ",", "?", "!", "\"", ":", ";", "[", "]", "/", "\\", "\r", "\n", "\t"), " ", $data));

		/*Разбиваем сторку на массив слов, к сожалению explode не подходить потому что нам потребуется
		Индекс начала каждого слова в строке, индексом будет служить ключ массива*/
		$words_array = array('0' => "");
		$data_length = strlen($data_ch);
		$word_start_idx = 0;
		for ($i=0; $i<$data_length; $i++) {
			if ($data_ch{$i} == " " && $i<$data_length && $data_ch{$i+1} != " ") {
				$word_start_idx = $i+1;
				$words_array[$word_start_idx] = "";
			} else {
				$words_array[$word_start_idx] .= $data_ch{$i};
			}
		}

		//Подготавливаем сложный массив, содержащий кроме слов еще и словосочетания
		$words_complex_array = array();
		$i=0;
		foreach ($words_array as $key => $word) {
			$tmp = array_slice($words_array, $i++, 3); //Вырезаем кусочки по 3 слова
			$tmp = array_pad($tmp, 3, ""); //Все кусочки должны содержать по 3 элемента
			$words_complex_array[$key] = array(
					implode(" ", $tmp),
					implode(" ", array($tmp[0], $tmp[1])),
					$tmp[0]
				);
		}

		//Zend_Debug::dump($words_array);
		//Zend_Debug::dump($words_complex_array);

		/*Проходимся по всем словам и словосочетаниям - ищем синонимы для каждого.
		После замены фиксируем смещение, которое было вызвано заменой на слово другой длины*/
		$offset = 0;
		$skip_words_groups = 0;
		foreach ($words_complex_array as $word_position => $words_array) {

			/*После замены словосочетания, нужно пропустить все группы, в которых учавствовали
			слова из этого словосочетания*/
			if ($skip_words_groups > 0) {
				$skip_words_groups--;
				continue;
			}

			foreach ($words_array as $key => $word) {
				$word = trim($word); //Обязательно удаляем пробелы
				/*Пропускаем словосочетания, содержащие более двух пробелов подряд
				Скорее всего эти слова разделены знаком препинания, их лучше не трогать*/
				if (strpos($word, "  ") !== false) {
					continue;
				}

				$synonyms_list = $this->getSynonymsList($word);

				//Производим замену на случайное слово
				if (!empty($synonyms_list)) {
					$word_replace = $synonyms_list[mt_rand(0, sizeof($synonyms_list)-1)];

					//Определяем слова с заглавной буквы и полностью заглавными
					if (strtolower($word) != $word) {
						if ($word == strtoupper($word)) {
							$word_replace = strtoupper($word_replace);
						} else {
							$word_replace = ucfirst($word_replace);
						}
					}

					$data = substr($data, 0, $word_position+$offset) . $word_replace . substr($data, $word_position+$offset+strlen($word));

					//Сохраняем статистику произведенных замен
					$result['stats'][] = array(
						'from' => $word,
						'to' => $word_replace,
						'from_pos' => $word_position,
						'to_pos' => $word_position+$offset
					);

					$offset += strlen($word_replace) - strlen($word);
					//echo $word . " => " . $word_replace . "<br/>";

					/*Замена найдена, сохраняем количество групп, в которые входили слова, которые
					подверглись замене, остальные варианты пропускаем в этой группе*/
					$skip_words_groups = 2 - $key;
					break;
				}
			}
		}

		return $result;
	}

}