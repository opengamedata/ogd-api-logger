<?php
$LOGGER_SCHEMA = "LOGGER";
$OGD_SCHEMA    = "OPENGAMEDATA";

   function schemaFromAppID($app_id) {
      $ret_val = $OGD_SCHEMA; // default to OGD

      $APP_ID = strtoupper($app_id);
      $logger_games = array("BACTERIA",   "BALLOON",  "CRYSTAL",    "CYCLE_CARBON", "CYCLE_NITROGEN", "CYCLE_WATER",
                              "EARTHQUAKE", "JOWILDER", "LAKELAND",   "MAGNET",       "WAVES",          "WIND");
      $ogd_games    = array("AQUALAB",    "BLOOM",    "ICECUBE",    "JOURNALISM",   "MASHOPOLIS",     "PENGUINS",
                              "THERMOVR",   "TRANSFORMATION_QUEST");
      // if in logger list, use logger
      if (in_array($APP_ID, $logger_games)) {
         $ret_val = $LOGGER_SCHEMA;
      }
      // if in OGD list, use OGD
      elseif (in_array($APP_ID, $ogd_games)) {
         $ret_val = $OGD_SCHEMA;
      }

      return $ret_val;
   }

   /**
   * An array of event data bodies, containing event_name, event_data, and similar columns.
   * @var array
   */
   function dataToArray($data) {
      # if the data was a single event, turn into a length-1 array.
      if (!is_array($data)) {
         $d = $data;
         $data = array();
         array_push($data, $d);
      }
      return $data;
   }
?>
