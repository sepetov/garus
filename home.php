<?php
/*
Я пока не буду никак заморачиваться с внешним видом этой страницы, а просто реализую её функционал. С шаблонами буду работать намного позже.

Потом же решу вопрос с тем, что созданную по умолчанию категорию "Всё моё" можно удалить с самого начала, не добавляя ничего нового. Как тогда себя поведёт программа?
*/

include('library.php');
require('authorization.php');

$title = 'Панель управления';
session_start();

$location_categories = 'files/categories.html';
if ( file_exists($location_categories) )
{
	$all_categories = get_from_accumulator($location_categories);
}
else
{
	// Создание категории по умолчанию:
	$name_category = "Моя лента";
	$all_categories[$name_category] = create_category($name_category);
	save_to_accumulator($location_categories, $all_categories);
}


// Пользователю даётся возмжность создавать ПО ОДНОЙ свои собственные категории, по которым он потом будет рассортировывать свои любимые рассылки:
if ( isset($_POST['name_created_category']) )
{
	$name_created_category = $_POST['name_created_category'];
	$it_is_new_category = find_categories($all_categories, $name_created_category);

	if ( $name_created_category <> '' and $it_is_new_category )
	{
		$all_categories[$name_created_category] = create_category($name_created_category);
		save_to_accumulator($location_categories, $all_categories);
	}
}

// Пользователь имеет возможность удалять ХОТЬ СРАЗУ НЕСКОЛЬКО ненужных ему категорий:
if ( isset($_POST['name_delete_category']) )
{
	$delete_categories = $_POST['name_delete_category'];
	$all_categories = array_diff_key($all_categories, $delete_categories);
	save_to_accumulator($location_categories, $all_categories); // похоже, что вместе с категорией удалится и её содержимое. Круто!
}

// В каждую категорию пользователь может добавить полюбившуюся рассылку:
if ( isset($_POST['select_category']) and isset($_POST['url_rss']) )
{
	$name_select_category = $_POST['select_category'];// тут необходимо, подобно find_categories(), проверить, вдруг рассылка уже существует в другой категории?
	$configuration_category = $all_categories[$name_select_category];

	$url_rss = $_POST['url_rss'];// а вот тут критически важна проверка корректности адреса. Сейчас сюда можно совать даже НЕ адреса, а случайные наборы символов. Тут же нужно вводить только адрес rss-ленты
	$xml_rss_channel = simplexml_load_file($url_rss); // а тут, возможно, проверка корректности адреса будет куда уместрей. Если функция вернула false, значит что-то не так.

	if ( $xml_rss_channel === false ) die('Введён некорректный URL для рассылки.');

	$name_rss_channel = $xml_rss_channel->channel->title;
	$name_rss_channel = (string) $name_rss_channel; // к сожалению, у нас в PHP нет строгой типизации, поэтому из title мы получили какой-то такой объект, что PHP не может узнать в нём строку. Используем явное приведение к строке. 
	$new_rss_channel_in_category[$url_rss] = $name_rss_channel;
	$configuration_category['all_channels'] = $configuration_category['all_channels'] + $new_rss_channel_in_category;
	$all_categories[$name_select_category] = $configuration_category;

// 1. Нужно следить, чтобы не дублировались не только категории, но и рассылки
// 2. Не очевидный момент: рассылки могут ведь дублироваться не только в пределах одной категории, но и в разных! Такое нужно исключать
// 3. А ещё, для удобочитаемости кода, неплохо получше изучить работу с многомерными массивами в PHP, чтобы не городить карусель из переприсвоения значений от одной переменной к другой и по нарастающей.
	insert_elements($location_categories, $all_categories);
}

// Разонравившиеся рассылки можно удалять:
if ( isset($_POST['delete_channel']))
{
	$delete_channels = array_keys($_POST['delete_channel']);
	$delete_channels = array_flip($delete_channels);

	foreach ($all_categories as $name_category => $configuration_category)
	{
		$new_configuration['location_accumulator'] = $configuration_category['location_accumulator'];
		$new_configuration['location_history'] = $configuration_category['location_history'];
		
		$all_channels = $configuration_category['all_channels'];
		$all_channels = array_diff_key($all_channels, $delete_channels);
		$new_configuration['all_channels'] = $all_channels;

		$new_version_all_categories[$name_category] = $new_configuration;
	}
	$all_categories = $new_version_all_categories;

	save_to_accumulator($location_categories, $all_categories);
}

$location_users = 'files/alias_name_user_and_passwords.txt';
if ( file_exists($location_users) )
{
	$all_users = get_from_accumulator($location_users);
}
else
{
	$all_users = array();
}

if ( isset($_POST['alias_and_passwords']) )
{
	$new_users_and_passwords = $_POST['alias_and_passwords'];
	$new_users_and_passwords = explode("=", $new_users_and_passwords);
	$count = count($new_users_and_passwords);
	if ( $count & 1 )
	{
		die('Введено нечётное количество пользователь/пароль! <a href="home.php">Вернуться</a>');
	}
	foreach ($new_users_and_passwords as $index => $user_or_password)
	{
		if ( strlen($user_or_password) < 1 ) die('Имя и пароль не могут быть нулевой длины! <a href="home.php">Вернуться</a>');
		if ( $index & 1 ) $new_passwords[] = $user_or_password;
		if ( !($index & 1) ) $new_users[] = $user_or_password;
	}
	// место для модернизации: код проверки имён и паролей лучше выделить в отдельную функцию. Пусть проверяет ещё и то, что может быть несколько одинаковых пользователей и паролей
	$count = count($new_users);
	for ($i = 0; $i < $count; $i = $i + 1)
	{ 
		$users[$new_users[$i]] = md5($new_passwords[$i]);
	}
	$all_users = $all_users + $users;
	save_to_accumulator('files/alias_name_user_and_passwords.txt', $all_users);
}

if ( isset($_POST['name_delete_user']) )
{
	$delete_users = $_POST['name_delete_user'];
	$all_users = array_diff_key($all_users, $delete_users);
	save_to_accumulator('files/alias_name_user_and_passwords.txt', $all_users);
}

include 'templates/start.html';
include 'templates/top_menu.html';
include 'templates/home.html';
include 'templates/finish.html';
?>