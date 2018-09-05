<?php
/*
Этот скрипт - основное рабочее место в программе. В нём пользователь видит свои сообщения, может их отмечать для будущего прочтения, а может очищать. Отсюда же он может попась в другие разделы программы (через меню)
*/


include('library.php');
require('authorization.php');

session_start();
$_SESSION['name_run_script'] = 'index.php';

$title = 'Увлекательное чтиво';
$count_elements_on_page = 10;
$chronology = False; // какие-то рассылки хочется выводить в хронологическом порядке, но большинство - наоборот.
$template_menu = 'templates/menu.html';
$template_lenta = 'templates/lenta.html';
$location_categories = 'files/categories.html';
$stop_scaner_file = 'files/stop_scaner_file.txt';

if ( file_exists($location_categories) )
{
	$all_categories = get_from_accumulator($location_categories);
}
else
{
	die('Рассылок ещё нет. Создайте хотя бы одну: <a href="home.php">служебная страница</a>.');
}

if ( isset($_GET['category']) )
{
	$active_category = $_GET['category'];
}
else
{
	$names_categories = array_keys($all_categories);
	$active_category = $names_categories[0];
}

$total_count_elements_in_categories = scan_elements_in_categories($all_categories);
$active_accumulator = $all_categories[$active_category]['location_accumulator'];
$active_history = $all_categories[$active_category]['location_history'];
$active_elements = get_from_accumulator($active_accumulator);
$visible_elements = get_visible_elements($count_elements_on_page, $chronology, $active_elements);

if ( isset($_POST['submit']) )
{
	if ( isset($_POST['interest_element']) )
	{
		$links_interest_elements = $_POST['interest_element'];
		$interest_elements = translate_links_to_elements($links_interest_elements, $visible_elements);
		$non_interest_elements = array_diff_key($visible_elements, $interest_elements);
	}
	else
	{
		$non_interest_elements = $visible_elements;
	}
	$active_elements = array_diff_key($active_elements, $non_interest_elements);
	save_to_accumulator($active_accumulator, $active_elements);
	save_to_history($active_history, $non_interest_elements);
	$visible_elements = get_visible_elements($count_elements_on_page, $chronology, $active_elements);
	$total_count_elements_in_categories = scan_elements_in_categories($all_categories);
}

include 'templates/start.html';
include 'templates/top_menu.html';
include $template_menu;
include $template_lenta;
include 'templates/finish.html';
?>