<?php
class MySQLUtils {

   static function Connect(string $servername, string $username, string $password, string $db) : mysqli|false {
      die("FAIL: Logging with MySQL is currently not supported!");

      try {
         $conn = mysqli_connect($servername, $username, $password, $db);
         if (!$conn) {
            http_response_code(500);
            error_log("Error when trying to connect to MySQL database: " . mysqli_connect_error());
            die("Could not connect to event database");
         }
      }
      catch (Exception $e) {
         http_response_code(500);
         error_log("Error when trying to connect to MySQL database: ".$e->getMessage());
         die("Could not connect to event database");
      }
      return $conn;
   }

   static function Insert(mysqli $conn, string $app_id, string $query) : string {
      die("FAIL: Logging with MySQL is currently not supported!");
      try {
         $result = mysqli_query($conn, $query);
         if (!$result) {
            $sql_err = "Query for ".$app_id." failed with error: ".mysqli_error($conn);
            error_log($sql_err);
            die("FAIL: ".$sql_err);
         }
      }
      catch(mysqli_sql_exception) {
         $app_version = NULL;
         if (isset($_REQUEST["app_version"])) {
            $app_version = filter_var($_REQUEST["app_version"], FILTER_SANITIZE_NUMBER_INT);
         }
         $sql_err = "Query for ".$app_id." failed with error: ".mysqli_error($conn);
         error_log($sql_err);
         die("FAIL: ".$sql_err);
      }
      return $query;
   }
}

?>
