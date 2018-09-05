<?php
session_start();

if ( isset($_GET['login']) and $_GET['login'] == 'false' )
{
	unset($_SESSION['user']);
	session_destroy();
	header("Location: login.php");
	exit;
}

if ( !isset($_SESSION['user']) )
{
	header("Location: login.php");
	exit;
}

if ( isset($_SESSION['user']) and $_SESSION['user'] == 'root' )
{
	$html_attribute_disabled = ''; // тут мы дали root'у максимальные права
}
else
{
	$html_attribute_disabled = 'disabled'; // а тут ограничили в правах всех остальных пользователей.
}
?>