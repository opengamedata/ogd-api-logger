<?php
header("Access-Control-Allow-Headers: Origin, Authorization, X-Requested-With, Content-Type, Accept");
header("Access-Control-Allow-Origin: *");

include('config.php');

$conn = mysqli_connect($servername, $username, $password, $db);
if(!$conn) die("Connection failed: " . mysqli_connect_error());

//per dump
if(isset($_REQUEST["class_id"]))  $class_id        = mysqli_real_escape_string($conn,$_REQUEST["class_id"]); else die("No class_id");
if(isset($_REQUEST["username"]))  $player_username = mysqli_real_escape_string($conn,$_REQUEST["username"]); else die("No username");
if(isset($_REQUEST["player_id"])) $player_id       = preg_replace("/[^a-zA-Z0-9]+/", "", $_REQUEST["player_id"]); else die("No player_id");

$query = "INSERT INTO players (".
  "class_id,".
  "username,".
  "player_id,".
  ") VALUES";

$query .=
  "(".
  "\"".$class_id."\",".
  "\"".$player_username."\",".
  "\"".$player_id."\",".
  ")";

if($n_rows > 0) mysqli_query($conn,$query);
die($query);

?>
