<?php
class MySQLUtils {
   static function Insert($conn, $app_id, $query) : string {
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
