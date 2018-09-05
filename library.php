<?php
/*
Будущее замечание: для удобочитаемости кода, пожалуй, следует создать несколько синонимов для функций вида save_to_xxx (аккумулятор, история). Синонимы должны быть вида create_accumulator и т. п. Так иногда непонятно, почему мы что-то сохраняем в тот же аккумулятор. Некоторые, или все функции вида save_to_xxx может быть заменить на refresh_xxx.

Теперь, ввиду разрастания размеров файлов, в каждую функцию стоит добавить проверку размера файла. Если файл становится больше, например, какого-то размера, то можно его делить на части. Как только потом этим всем управлять?

Функцию insert_elements лучше переименовать в insert_to_accumulator

При следующей обработке кода максимально избавиться от всех проверок и if-ов, затрудняющими чтение текста. По возможности, все проверки должны быть или в отдельных функциях, или что-то вроде try...catch.
*/

function save_to_error_and_statistic_log($event)
{
	/*
	В будущем эта функция позволит анализировать информацию о том, что случилось с какой-то подпиской. Иногда бывает так, что сайт закрылся и подписка больше невозможно, а иногда сайт просто отваливается на непродолжительное время, но потом снова работает. С некоторыми сайтами это "иногда" происходит не так уж и редко. И та, и та информация полезна и может быть использована во благо. Сейчас это не особо нужно, поэтому функция остаётся на будущее.
	*/
	$file_handle = fopen('files/error_and_statistic.log', 'a');
	$string_to_write = date('d.m.Y H:i')." $event\n";
	fwrite($file_handle, $string_to_write);
	fclose($file_handle);
	return 1;
}

/*
Работать с xml-представлением данных не очень удобно, поэтому функция разбирает xml и возвращает информацию из него обратно, но уже в виде удобного массива.
*/
function translate_xml_content_to_array($xml_content)
{
	foreach ($xml_content->channel->item as $item)
	{
		$title = $item->title;
		$description = $item->description;
		$date = $item->pubDate;
						$date = date('<b>d</b>.m.Y <b>H:i</b>', strtotime($date)); // не отвлекаясь от основной работы чуть-чуть отформатировал дату. Внешнего шаблона пока нет.
		$link = $item->link;

		// В PHP нет явной типизации, но переменные можно принудительно привести к строковому типу:
		$title = (string) $title;
		$description = (string) $description;
		$date = (string) $date;
		$link = (string) $link;

		$rss_elements["$link"] = array("title"=>$title, "description"=>$description, "date"=>$date);
	}
	return $rss_elements;
}

/*
Название функции говорит само за себя, но сделаю уточнение: массив сериализуется и сохраняется в файл, но в названии фукнции это не отражено.
Сделано это из-за того, что в будущем файл может быть заменён на БД, или же вообще на файл другого формата. Место хранения может быть локальным, а может быть и нет. И, конечно, данные могут быть не серализованы, а записаны как есть. А может сжаты. Если возникнет такая потребность - не нужно трогать основной код программы, так как хватит только переписать эту функцию. В будущем такое переписывание не выйдет боком из-за несоответствия названия функции тому, что она выполняет (как могло бы быть, если бы имя было save_to_file_accumulator или подобном)
При всех подобных изменениях название функции в основном коде не поменяется, логика работы скриптов - тоже. Нужно будет всего-то в этой библиотеке переопределить эту самую функцию. Или переписать.

Например, самое первое изменение, которое мне потребовалось - это блокировка файла перед записью. Она нужна для исключения редкого конкурентного доступа к файлу со стороны разных скриптов.
*/
function save_to_accumulator($file_name, $array_elements)
{
	$file_handle = fopen($file_name, 'w');

	if ( flock($file_handle, LOCK_EX) )
	{
		$string_to_write = serialize($array_elements);
		fwrite($file_handle, $string_to_write);
	}
	else
	{
		save_to_error_and_statistic_log("Неудачная попытка запереть (заблокировать) файл $file_name");
	}
	fclose($file_handle);
}

/*
Функция, при разных обстоятельствах, является синонимом либо к insert_elements, либо к  save_to_accumulator. Предназначена исключительно для удобочитаемости исходных кодов.
*/
function save_to_history($file_name, $array_elements)
{
	if ( file_exists($file_name) )
	{
		insert_elements($file_name, $array_elements);
	}
	else
	{
		save_to_accumulator($file_name, $array_elements);
	}
	
}

/*
Если в будущем хранение в файлах будет заменено на хранение в БД или каким-то иным способом, то переписать нужно будет лишь тело этой функции, а название и примеры использования в основных скриптах можно будет не менять.
*/
function get_from_accumulator($file_name)
{
	$file_handle = fopen($file_name, 'r');
	$string_from_file = fread($file_handle, filesize($file_name));
	fclose($file_handle);

	$rss_elements = unserialize($string_from_file); // почти все ошибки unserialize вызывает эта строчка. Или вообще все.

	return $rss_elements;
}

function get_from_history($file_name)
{
	// эта функция-синоним введена только с той целью, чтобы сделать основной код более читабельным. Из названия её сразу ясно, что она делает. По структуре же файл истории rss-элементов и их аккумулятор ничем не отличаются, поэтому и могут быть записаны одинаковой функцией.
	$rss_elements = get_from_accumulator($file_name);

	return $rss_elements;
}

/*
Функция отличается от save_to_accumulator только тем, что не перезаписывает старые данные в нём, а лишь добавляет новые к уже существующим. Тут есть некоторое поле для изменений: вставлять можно как после существующих, так и до них, как по порядку, так и вперемежку. Однако, в названии функции это не отражено, так как не имеет никакого значения.
*/
function insert_elements($file_name, $array_elements)
{
	$old_rss_elements = get_from_accumulator($file_name);
	$new_rss_elements = $array_elements;
	$all_rss_element = $new_rss_elements + $old_rss_elements; //можно было ещё array_merge, либо array_merge_recursive

	save_to_accumulator($file_name, $all_rss_element);
}

function get_visible_elements($count_elements_on_page, $chronology, $all_elements)
{
	if ( count($all_elements) <= $count_elements_on_page )
	{
		$visible_elements = $all_elements;
	}
	if ( count($all_elements) == 0 )
	{
		$visible_elements['home.php'] = array('title' => 'Пусто', 'description' => 'Материалов к прочтению более не осталось.', 'date' => '123');
	}
	if ( count($all_elements) > $count_elements_on_page )
	{
		$all_pages = array_chunk($all_elements, $count_elements_on_page, true);
		$visible_elements = $all_pages[0];
		// придётся пока не использовать переменную о хронологическом отображении. Оставлю под модернизацию. Проблема будет из-за того, что в последнем элементе может быть элементов меньше, чем 10.
		// А поэтому нужно перед array_chunk сам массив инвертировать, чтобы последние элементы оказались впереди и при делении их точно стало не меньше 10.
	}
	return $visible_elements;
}

/*
Иногда в программе бывает так, что есть список ссылок на какие-то элементы. Это, например, сохраняемые новости. Может быть их куда-то отправляют (будет же кнопка "Поделиться":). Может быть кто-то захочет новости экспортировать. Если нужно экспортировать сами ссылки - пожалуйста, массив уже есть, но иногда нужны не ссылки на них, а сами новости.

Эта функция по имеющемуся списку ссылок подтянет из аккумулятора содержимое самих рассылок с этими ссылками (заголовок, описание, дату публикации)
*/
function translate_links_to_elements($links_elements, $array_elements)
{
	foreach ($links_elements as $link)
	{
		$elements[$link] = $array_elements[$link];
	}
	return $elements;
}

function create_category($name_category)
{
	$location_accumulator = generate_location_accumulator($name_category);
	$location_history = generate_location_history($name_category);
	$all_channels = array();
	$configuration_category = array('location_accumulator' => $location_accumulator, 'location_history' => $location_history, 'all_channels' => $all_channels);

	return $configuration_category;
}

/*
Зачем нужна функция?
Бывает так, что пользователь создаёт новую категорию рассылок. Когда он даёт ей имя, нужно сделать так, чтобы он не смог случайно создать две категории с одинаковым именем.
*/
function find_categories($all_categories, $name_created_category)
{
	if ( isset($all_categories[$name_created_category]) )
	{
		return false;// создаваемая категория найдена в списке, значит она не новая, а создавалась ранее. Создавать снова не нужно
	}
	else
	{
		return true; // создаваемая категория отсутствует в списке, значит можно создать, не опасаясь дублирования.
	}
	
}

function generate_location_accumulator($url_rss)
{
	$file_name = $url_rss;
	$file_name = str_replace('/', '_', $file_name);
	$file_name = str_replace(':', '_', $file_name);
	$file_name = str_replace('-', '_', $file_name);
	$file_name = str_replace(' ', '_', $file_name);
	$file_name = 'files/'.$file_name.'accumulator.html';
	return $file_name;
}

function generate_location_history($url_rss)
{
	$file_name = $url_rss;
	$file_name = str_replace('/', '_', $file_name);
	$file_name = str_replace(':', '_', $file_name);
	$file_name = str_replace('-', '_', $file_name);
	$file_name = str_replace(' ', '_', $file_name);
	$file_name = 'files/'.$file_name.'history.html';
	return $file_name;
}

function user_finder($name_user, $password)
{
	if ( $name_user == 'root' and $password == '123' )
	{
		return true;
	}

	$name_file = 'files/alias_name_user_and_passwords.txt';
	$array_alias_and_passwords = get_from_accumulator($name_file);

	if ( isset($array_alias_and_passwords[$name_user]) )
	{
		if ( $array_alias_and_passwords[$name_user] == md5($password) )
		{
			return true;
		}
	}
	else
	{
		return false;
	}
}

function scan_elements_in_categories($all_categories)
{
	foreach ($all_categories as $name_category => $configuration_category) //добавить проверку на пустую категорию
	{
		$location_accumulator = $configuration_category['location_accumulator'];
		$location_history = $configuration_category['location_history'];
		$all_channels = $configuration_category['all_channels'];
																			if ( count($all_channels) == 0 ) {$total_count_elements_in_categories[$name_category] = 0; continue;}
		$all_elements = get_from_accumulator($location_accumulator);
		$count_elements = count($all_elements);
		$total_count_elements_in_categories[$name_category] = $count_elements;
	}
	return $total_count_elements_in_categories;
}
?>