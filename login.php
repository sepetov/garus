<?php
include('library.php');

session_start();
$request_page = 'index.php';

if ( isset($_SESSION['user']) )
{
	header("Location: $request_page");
	exit;
}

if ( isset($_POST['click_login']) )
{
	$user = $_POST['user'];
	$password = $_POST['password'];
	if ( user_finder($user, $password) )
	{
		save_to_error_and_statistic_log("Вошёл пользователь $user");

		$_SESSION['user'] = $user;
		header("Location: $request_page");
		exit;
	}
	else
	{
		save_to_error_and_statistic_log("Неудачная попытка входа: используемое имя - $user, используемый пароль - $password");

		print("Неверные имя или пароль.");
	}
}

$title = 'Вход';
include('templates/start.html');
?>
<form action="" method="post" class="form-inline">
	Имя: <input class="form-control" type="text" name="user">
	Пароль: <input class="form-control" type="password" name="password">
	<input type="submit" name="click_login" value="Войти" class="btn btn-primary">
</form>
<?php
include('templates/finish.html');
?>