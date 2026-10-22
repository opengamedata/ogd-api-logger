<?php declare(strict_types=1);

   use PHPUnit\Framework\TestCase;
   use Logger\Event;

   final class EventBasicCase extends TestCase
   {
      private static array $vals;
      private $event;
      protected function setUp() : void
      {
         EventBasicCase::$vals  = [
            "game_id" => "TEST_GAME",
            "instance_id" => "fake_instance", 
            "player_id" =>  "TestUser", 
            "session_id" =>  "fake_session",  // use a fake number that is always different so we don't get errors for duplicate event_sequence_index
            "game_version" =>  "1.0.0-testbed", 
            "schema_version" =>  "1.0-alpha", 
            "log_version" =>  "1", 
            "condition" =>  "testing-branch", 
            "game_config" =>  "{}", 
            "platform" =>  "{}", 
            "player_history" =>  "{}", 
            "timestamp" => DateTimeImmutable::createFromFormat("Y-m-d H:i:s.uT", "2020-01-01 13:42:56.789Z"), 
            "auth_timestamp" => DateTimeImmutable::createFromFormat("Y-m-d H:i:s.uT", "2020-01-01 13:43:56.789Z"), 
            "game_time" =>  10.0, 
            "sequence_index" =>  1, 
            "game_segment" =>  json_encode(["level" => 1]), 
            "game_state" =>  json_encode(["state_var" => "active"]), 
            "event_id" =>  9999, 
            "event_name" =>  "fake_event", 
            "event_data" =>  json_encode(["some_element" => "some_value", "some_int" => 0]), 
         ];
         $this->event = new Event(
            game_id:"TEST_GAME",
            instance_id: EventBasicCase::$vals["instance_id"],
            player_id: EventBasicCase::$vals["player_id"],
            session_id: EventBasicCase::$vals["session_id"],
            game_version: EventBasicCase::$vals["game_version"],
            schema_version: EventBasicCase::$vals["schema_version"],
            log_version: EventBasicCase::$vals["log_version"],
            condition: EventBasicCase::$vals["condition"],
            game_config: EventBasicCase::$vals["game_config"],
            platform: EventBasicCase::$vals["platform"],
            player_history: EventBasicCase::$vals["player_history"],
            timestamp: EventBasicCase::$vals["timestamp"],
            auth_timestamp: EventBasicCase::$vals["auth_timestamp"],
            game_time: EventBasicCase::$vals["game_time"],
            sequence_index: EventBasicCase::$vals["sequence_index"],
            game_segment: EventBasicCase::$vals["game_segment"],
            game_state: EventBasicCase::$vals["game_state"],
            event_id: EventBasicCase::$vals["event_id"],
            event_name: EventBasicCase::$vals["event_name"],
            event_data: EventBasicCase::$vals["event_data"]
         );
      }

      public function testConstructor(): void
      {
         $result = new ReflectionClass($this->event);

         $observed = $result->getProperty('game_id')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["game_id"],
            "Incorrect game ID ".$observed."!"
         );
         $observed = $result->getProperty('instance_id')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["instance_id"],
            "Incorrect instance ID ".$observed."!"
         );
         $observed = $result->getProperty('player_id')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["player_id"],
            "Incorrect player ID ".$observed."!"
         );
         $observed = $result->getProperty('session_id')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["session_id"],
            "Incorrect session ID ".$observed."!"
         );
         $observed = $result->getProperty('timestamp')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["timestamp"],
            "Incorrect timestamp ".($observed ? $observed->format("Y-m-d H:i:s.u") : $observed)."!"
         );
         $observed = $result->getProperty('authoritative_timestamp')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["authoritative_timestamp"],
            "Incorrect authoritative timestamp ".($observed ? $observed->format("Y-m-d H:i:s.u") : $observed)."!"
         );
         $observed = $result->getProperty('game_time')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["game_time"],
            "Incorrect game time ".$observed."!"
         );
         $observed = $result->getProperty('session_sequence_index')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["session_sequence_index"],
            "Incorrect sequence index ".$observed."!"
         );
         $observed = $result->getProperty('game_segment')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["game_segment"],
            "Incorrect game segment ".$observed."!"
         );
         $observed = $result->getProperty('event_source')->getValue($this->event);
         $this->assertSame(
            $observed,
            "GAME",
            "Incorrect event source ".$observed."!"
         );
         $observed = $result->getProperty('source_version')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["game_version"],
            "Incorrect source version ".$observed."!"
         );
         $observed = $result->getProperty('game_version')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["game_version"],
            "Incorrect game version ".$observed."!"
         );
         $observed = $result->getProperty('schema_version')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["schema_version"],
            "Incorrect schema version ".$observed."!"
         );
         $observed = $result->getProperty('log_version')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["log_version"],
            "Incorrect log version ".$observed."!"
         );
         $observed = $result->getProperty('condition')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["condition"],
            "Incorrect condition ".$observed."!"
         );
         $observed = $result->getProperty('game_configuration')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["game_configuration"],
            "Incorrect game config ".$observed."!"
         );
         $observed = $result->getProperty('platform')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["platform"],
            "Incorrect platform ".$observed."!"
         );
         $observed = $result->getProperty('game_state')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["game_state"],
            "Incorrect game state ".$observed."!"
         );
         $observed = $result->getProperty('player_history')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["player_history"],
            "Incorrect player history ".$observed."!"
         );
         $observed = $result->getProperty('event_id')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["event_id"],
            "Incorrect event ID ".$observed."!"
         );
         $observed = $result->getProperty('event_name')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["event_name"],
            "Incorrect event name ".$observed."!"
         );
         $observed = $result->getProperty('event_data')->getValue($this->event);
         $this->assertSame(
            $observed,
            EventBasicCase::$vals["event_data"],
            "Incorrect event data ".$observed."!"
         );
      }

      // public function testOGDLegacy_OddOffsets(): void
      // {
      //    $game_id = "TEST_GAME";
      //    $url_params = [
      //       "app_id" => $game_id,
      //       "session_id" => time(), // use a fake number that is always different so we don't get errors for duplicate event_sequence_index
      //       "user_id" => "TestUser",
      //       "user_data" => "{}",
      //       "app_version" => "1",
      //       "app_branch" => "testing-branch",
      //       "log_version" => "1"
      //    ];
      //    $body_params = [
      //       [
      //          "client_time" => "2020-01-01 13:42:56.789", //date('Y-m-d H:i:s'),
      //          "client_offset" => "03:00:00",
      //          "event_name" => "fake_event",
      //          "event_data" => json_encode(["some_element" => "some_value", "some_int" => 0]),
      //          "game_state" => json_encode(["state_var" => "active"]),
      //          "event_sequence_index" => 1
      //       ],
      //       [
      //          "client_time" => "2020-01-01 13:42:57.123", //date('Y-m-d H:i:s'),
      //          "client_offset" => "-04:00:00",
      //          "event_name" => "another_event",
      //          "event_data" => json_encode(["some_element" => "new_value", "some_int" => 42]),
      //          "game_state" => json_encode(["state_var" => "inactive"]),
      //          "event_sequence_index" => 2
      //       ]
      //    ];

      //    $request = curl_init();
      //    $test_url = $_ENV['base_url']."/log.php?".http_build_query($url_params);
      //    $opts = [
      //       CURLOPT_URL => $test_url,
      //       CURLOPT_USERAGENT => "fake agent/1.0",
      //       CURLOPT_HTTPHEADER => LogRemoteCase::$headers,
      //       CURLOPT_POST => true,
      //       CURLOPT_POSTFIELDS => ["data" => base64_encode(json_encode($body_params))],
      //       CURLOPT_POSTREDIR => CURL_REDIR_POST_302,
      //       CURLOPT_FOLLOWLOCATION => true,
      //       CURLOPT_RETURNTRANSFER => true
      //    ];
      //    curl_setopt_array($request, $opts);

      //    $response = curl_exec($request);
      //    $code = curl_getinfo($request, CURLINFO_HTTP_CODE);

      //    $this->assertSame(
      //       $code, 200,
      //       "Test Fail: Unexpected response code '".$code."' from call to ".$test_url."\nMessage: ".$response
      //    );
      //    $this->assertSame(
      //       $response, "SUCCESS: Inserted 2 rows to BigQuery in ".$game_id.".".$game_id."_daily_".(new DateTime())->format("Ymd").".",
      //       "Test Fail: Unexpected result '".$response."' from call to ".$test_url
      //    );
      // }

      // public function testOGDStandardCall(): void
      // {
      //    $game_id = "TEST_GAME";
      //    $url_params = [
      //       "game_id" => $game_id,
      //       "instance_id" => "fake_instance",
      //       "player_id" => "TestUser",
      //       "session_id" => time(), // use a fake number that is always different so we don't get errors for duplicate event_sequence_index
      //       "game_version" => "1.0.0-testbed",
      //       "schema_version" => "1.0-alpha",
      //       "log_version" => "1",
      //       "condition" => "testing-branch",
      //       "game_configuration" => "{}",
      //       "platform" => "{}",
      //       "user_data" => "{}"
      //    ];
      //    $body_params = [
      //       [
      //          "timestamp" => "2020-01-01 13:42:56.789Z", // date('Y-m-d\TH:i:s.uT'),
      //          "authoritative_timestamp" => date('Y-m-d\TH:i:s.uT'),
      //          "game_time" => "00:15:49.345",
      //          "session_sequence_index" => 1,
      //          "game_segment" => json_encode(["level" => 1]),
      //          "game_state" => json_encode(["state_var" => "active"]),
      //          "event_id" => 9999,
      //          "event_name" => "fake_event",
      //          "event_data" => json_encode(["some_element" => "some_value", "some_int" => 0])
      //       ],
      //       [
      //          "timestamp" => "2020-01-01 13:42:57.123+01:00", // date('Y-m-d\TH:i:s.uT'),
      //          "authoritative_timestamp" => date('Y-m-d\TH:i:s.uT'),
      //          "game_time" => "00:15:49.345",
      //          "session_sequence_index" => 2,
      //          "game_segment" => json_encode(["level" => 1]),
      //          "game_state" => json_encode(["state_var" => "inactive"]),
      //          "event_id" => 9999,
      //          "event_name" => "another_event",
      //          "event_data" => json_encode(["some_element" => "new_value", "some_int" => 42])
      //       ]
      //    ];

      //    $request = curl_init();
      //    $test_url = $_ENV['base_url']."/log.php?".http_build_query($url_params);
      //    $opts = [
      //       CURLOPT_URL => $test_url,
      //       CURLOPT_USERAGENT => "fake agent/1.0",
      //       CURLOPT_HTTPHEADER => LogRemoteCase::$headers,
      //       CURLOPT_POST => true,
      //       CURLOPT_POSTFIELDS => ["data" => base64_encode(json_encode($body_params))],
      //       CURLOPT_POSTREDIR => CURL_REDIR_POST_302,
      //       CURLOPT_FOLLOWLOCATION => true,
      //       CURLOPT_RETURNTRANSFER => true
      //    ];
      //    curl_setopt_array($request, $opts);

      //    $response = curl_exec($request);
      //    $code = curl_getinfo($request, CURLINFO_HTTP_CODE);

      //    $this->assertSame(
      //       $code, 200,
      //       "Test Fail: Unexpected response code '".$code."' from call to ".$test_url."\nMessage: ".$response
      //    );
      //    $this->assertSame(
      //       $response, "SUCCESS: Inserted 2 rows to BigQuery in ".$game_id.".".$game_id."_daily_".(new DateTime())->format("Ymd").".",
      //       "Test Fail: Unexpected result '".$response."' from call to ".$test_url
      //    );
      // }
   }

?>