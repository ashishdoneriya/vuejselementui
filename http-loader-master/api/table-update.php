<?php

header("Access-Control-Allow-Methods: POST");

include_once './config/database.php';
session_start();
$userId = $_SESSION['userId'];
if ($userId == null) {
	header('HTTP/1.0 401 Unauthorized');
	echo 'You are not authorized.';
	return;
}
$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents('php://input'), TRUE);
$data = json_decode(htmlspecialchars(strip_tags(json_encode($data))));
$displayedTableName = $data->displayedTableName;
$tableName = $data->tableName;
$newFields = $data->fields;
$idsFound = 0;
foreach($newFields as $field) {
	if ($field->type == 'Id') {
		$idsFound++;
	}
	$field->id = str_replace(' ', '_', $field->name);
}
if ($idsFound == 0) {
	echo '{"status" : "failed", "message" : "No Id provided" }';
	return;
}
if ($idsFound > 1) {
	echo '{"status" : "failed", "message" : "Multiple Ids provided" }';
	return;
}
$rows = $db->query("select fields from users_tables where tableName='$tableName' and userId=$userId");
$row = $rows->fetch();
if (gettype($row) == 'boolean' && $row == false) {
	header('HTTP/1.0 401 Unauthorized');
	echo 'You are not authorized.';
	return;
}
$oldFields = json_decode($row['fields']);
foreach($newFields as $newField) {
	if (!property_exists($newField, 'id')) {
		$newField->id = str_replace(' ', '_', $newField->name);
		// Adding column
		$db->query("alter table " . $tableName . " add " . $newField->id . " " . getMysqlFieldType($newField->type) . getRequired($newField->isCompulsory));
	} else {
		// Modifying column
		$db->query("alter table " . $tableName . " modify column " . $newField->id . " " . getMysqlFieldType($newField->type) . getRequired($newField->isCompulsory));
	}
}

foreach($oldFields as $oldField) {
	$isExists = false;
	foreach ($newFields as $newField) {
		if ($oldField->id == $newField->id) {
			$isExists = true;
		}
	}
	if ($isExists == false) {
		// Removing fields
		$db->query("alter table " . $tableName . " drop column " . $oldField->id);
	}
}

// updating users_tables
$encodedFields = json_encode($newFields);
$query = "update users_tables set displayedTableName='$displayedTableName',fields='$encodedFields' where userId=$userId and tableName='$tableName'";
$rows = $db->query($query);

if ($rows == false) {
	echo '{"status" : "failed", "message" : "Unable to add the table, internal error" }';
} else {
	echo '{"status" : "success"}';
}

function getRequired($required) {
	if ($required == true) {
		return ' NOT NULL';
	}
	return '';
}
function getMysqlFieldType($type) {
	switch ($type) {
		case 'Text' :
		case 'Select' :
		case 'Checkbox' :
		case 'Radio Button' :
			return 'TEXT';
		case 'Number' :
			return 'BIGINT';
		case 'Decimal Number' :
			return 'DOUBLE(M,D)';
		case 'Date' :
			return 'DATE';
		case 'Time' :
			return 'TIME';
		case 'Date Time' :
			return 'DATETIME';
		default :
			return 'TEXT';
	}
}
?>