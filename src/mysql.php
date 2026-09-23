<?php
class MySQLUtils {
   static function InsertMySQL($conn, $app_id, $query) {
      $result = mysqli_query($conn, $query);
      if (!$result) {
         $sql_err = "Query for ".$app_id." failed with error: ".mysqli_error($conn);
         error_log($sql_err);
         die("FAIL: ".$sql_err);
      }
   }
}

?>
