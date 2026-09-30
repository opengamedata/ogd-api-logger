<?php declare(strict_types=1);
   use PHPUnit\Framework\TestCase;

   final class LogRemoteCase extends TestCase
   {
      public function testBasicCall(): void
      {
         $url_params = [
            "session_id" => time(), // use a fake number that is always different so we don't get errors for duplicate event_sequence_index
            "user_id" => "TestUser",
            "user_data" => "{}",
            "app_version" => "1.0.0-testbed",
            "app_branch" => "testing-branch",
            "log_version" => "1"
         ];
         $body_params = [
            [
               "client_time" => date('Y-m-d H:i:s'),
               "client_offset" => 0,
               "event_name" => "fake_event",
               "event_data" => json_encode(["some_element" => "some_value", "some_int" => 0]),
               "game_state" => json_encode(["state_var" => "active"]),
               "event_sequence_index" => 1
            ],
            [
               "client_time" => date('Y-m-d H:i:s'),
               "client_offset" => 0,
               "event_name" => "another_event",
               "event_data" => json_encode(["some_element" => "new_value", "some_int" => 42]),
               "game_state" => json_encode(["state_var" => "inactive"]),
               "event_sequence_index" => 2
            ]
         ];

         $request = curl_init();
         $test_url = $_ENV['base_url']."/log.php?".http_build_query($url_params);
         $opts = [
            CURLOPT_URL => $test_url,
            CURLOPT_USERAGENT => "fake agent/1.0",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ["data" => base64_encode(json_encode($body_params))],
            CURLOPT_POSTREDIR => CURL_REDIR_POST_302,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true
         ];
         curl_setopt_array($request, $opts);

         $response = curl_exec($request);
         $code = curl_getinfo($request, CURLINFO_HTTP_CODE);

         $this->assertSame(
            $code, 200,
            "Test Fail: Unexpected response code '".$code."' from call to ".$test_url
         );
         $this->assertSame(
            $response, "SUCCESS: Dummy insert of 2 events into BQ.",
            "Test Fail: Unexpected result '".$response."' from call to ".$test_url
         );
      }
   }

?>