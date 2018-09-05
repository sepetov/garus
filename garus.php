<?php
/*
Это - скрипт-обозреватель, который обходит сайт (список сайтов) и ищет в них новые элементы RSS.
Скрипт запускается либо вручную (разумеется, тогда полнота найденной информации целиком и полностью ложится на того, кто запускает скрипт), либо автоматически сервером. Для последней цели можно, например, использовать cron.
*/

include('library.php');

session_start();
if (isset($_SESSION['name_run_script']) and $_SESSION['name_run_script'] == 'index.php')
{
	die('В данный момент программа используется. Поиск будет запущен позднее');
}
#$_SESSION['name_run_script'] = 'garus.php';


/*
А ещё нужно проверить, что будет с файлами, если закрыть garus.php в время работы.
*/
$location_categories = 'files/categories.html';
if ( file_exists($location_categories) )
{
	$all_categories = get_from_accumulator($location_categories);
}
else
{
	die('Рассылок ещё нет. Создайте хотя бы одну: <a href="home.php">служебная страница</a>.');
}

foreach ($all_categories as $name_category => $configuration_category)
{
	$location_accumulator = $configuration_category['location_accumulator'];
	$location_history = $configuration_category['location_history'];
	$all_channels = $configuration_category['all_channels'];

	foreach ($all_channels as $url_rss => $name_rss_channel)
	{
		$xml_content = simplexml_load_file($url_rss);
		if ( $xml_content === false )
		{
			print("Произошла ошибка при запросе $url_rss");
			save_to_error_and_statistic_log("Произошла ошибка при запросе $url_rss");
			continue;
		}

		$all_elements = translate_xml_content_to_array($xml_content);

		if ( file_exists($location_accumulator) == false )
		{
			save_to_accumulator($location_accumulator, $all_elements);
			save_to_history($location_history, array());
			continue;
		}

		$old_elements_from_accumulator = (array) get_from_accumulator($location_accumulator);// на этот момент файлы могут быть пусты!!!
		$old_elements_from_history = (array) get_from_history($location_history);
		$new_elements = array_diff_key($all_elements, $old_elements_from_accumulator, $old_elements_from_history);

		if ( count($new_elements) > 0 )
		{
			insert_elements($location_accumulator, $new_elements);
		}
		//тут стоит проверить необходимость прервать цикл: вдруг пользователь в этот момент зашёл в программу и нужно передать управление ему?
		
	}
}

#$_SESSION['name_run_script'] = '';
?>