<?php

header("Access-Control-Allow-Methods: POST");

include_once './config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), TRUE);
$email = htmlspecialchars(strip_tags($data['email']));
$password = htmlspecialchars(strip_tags($data['password']));
if (!$email || !$password) {
	echo '{"status" : "failed", "message" : "Please enter valid information"}';
	return;
}

$ps = $db->prepare("select count(*) from users where email=:email");
$ps->bindValue(':email', $email, PDO::PARAM_STR);
$ps->execute();
if($ps->rowCount() == 0) {
	echo '{"status" : "failed", "message" : "Email not registered"}';
	return;
}

$ps = $db->prepare("select userId, name from users where email=:email and password=:password");
$ps->bindValue(':email', $email, PDO::PARAM_STR);
$ps->bindValue(':password', sha1($password), PDO::PARAM_STR);
$ps->execute();
if($ps->rowCount() == 0) {
	echo '{"status" : "failed", "message" : "Invalid password"}';
	return;
} else {
	$row = $ps->fetch(PDO::FETCH_ASSOC );
	session_start();
	$_SESSION["userId"] = $row["userId"];
	$_SESSION["name"] = $row["name"];
	$_SESSION["email"] = $email;
	echo '{"status" : "success", "email" : "' . $email . '"}';
}

?>
