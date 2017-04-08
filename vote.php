<?php

//check if the user with given exists in the pdo
function userIsRegistered($pdo, $id) {
	$request = "SELECT * FROM users WHERE id = :id";
	$args = array('id' => $id);
	$stmt = $pdo->prepare($request, $args);
	$stmt->execute($args);
	return ($stmt->rowCount() > 0);
}

// register the given user
function registerUser($pdo, $id, $name, $birthdate, $gender, $email) {
	$request = "INSERT INTO users(id, name, birthdate, gender, email)
						VALUES(:id, :name, :birthdate, :gender, :email)";
	$args = array(	"id" 		=> $id,
					"name" 		=> $name,
					"birthdate" => $birthdate,
					"gender" 	=> $gender,
					"email" 	=> $email);
	$stmt = $pdo->prepare($request, $args);
	$stmt->execute($args);
}

function connectPDO() {
	//database connection
	$pdo = new PDO('mysql:dbname=sondageapp;host=localhost;charset=utf8', 'root', '');
	if ($pdo == NULL) {
		return (NULL);
	} 
	$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return ($pdo);
}

// get the given user unique ID for the given social network
// 		- social_id is in {0, 1, 2} meaning {facebook, google, twitter}, and
// 		- access_token is the login token of the given login platform
function getUserIDFromToken($social_id, $access_token) {

	if ($social_id == "0") {
		$url = "https://graph.facebook.com/me?access_token=" . $access_token;
		$headers = get_headers($url);
		$rcode = substr($headers[0], 9, 3);
		if ($rcode != "200") {
			return (NULL);
		}
		$userdata = json_decode(file_get_contents($url), TRUE);
		return ($userdata["id"]);
	}

	return (NULL);
}

// do the vote, register value in database
function vote($pdo, $id, $value) {

}

// check every variables are set
if (isset($_GET['social_id']) && isset($_GET['access_token']) && isset($_GET['value'])) {

	//log to database
	$pdo = connectPDO();

	//store them
	$social_id 		= $_GET['social_id'];
	$access_token 	= $_GET['access_token'];
	$value 			= $_GET['value'];

	// check user is logged in
	$userID = getUserIDFromToken($social_id, $access_token);
	if ($userID == NULL) {
		// then user isnt logged in
		print("wrong token for given social network");
		http_response_code(401);
	} else {
		$id = $social_id . $userID;

		//if user is not registered
		if (!userIsRegistered($pdo, $id)) {
			//register it
			if (!isset($_GET['gender']) || !isset($_GET['email']) || !isset($_GET['birthdate']) || !isset($_GET['name'])) {
				print("non-registered user should give additional GET arguments: 'gender', 'email', 'birthdate', 'name'");
				http_response_code(400);
			} else {

				print("registrating user...<br>");

				//check each parameters

				//ensure gender
				$gender = $_GET['gender'];
				if ($gender == "0") {
					$gender = 0;
				} else if ($gender == "1") {
					$gender = 1;
				} else {
					print("invalid gender");
					http_response_code(500);
					exit();
				}

				//ensure email frormat
				$email = $_GET['email'];
				if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
					print("invalid email");
					http_response_code(500);
					exit();
				}

				//ensure date format
				$birthdate = $_GET['birthdate'];
				if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $birthdate)) {
					print("invalid birthdate");
					http_response_code(500);
					exit();
				}

				//ensure len(name) < 64
				$name = $_GET['name'];
				if (strlen($name > 64)) {
					$name = substr($name, 0, 64);
				}

				//register the valid user
				registerUser($pdo, $id, $name, $birthdate, $gender, $email);
				print("user registered<br>");

				//do the vote
				vote($pdo, $id, $value);
				print("vote registered<br>");
				http_response_code(200);
			}
		} else {
			//user is already register, lets vote
			vote($pdo, $id, $value);
			print("vote registered<br>");
			http_response_code(200);
		}
	}
} else {
	print("Missing one of these GET arguments: 'social_id', 'access_token', 'value'");
	http_response_code(500);
}

?>