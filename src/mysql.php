<?php
class MySQLUtils {
   static function Insert($conn, $app_id, $query) : string {
      $result = mysqli_query($conn, $query);
      if (!$result) {
         $sql_err = "Query for ".$app_id." failed with error: ".mysqli_error($conn);
         error_log($sql_err);
         die("FAIL: ".$sql_err);
      }
      return $query;
   }
}

?>
