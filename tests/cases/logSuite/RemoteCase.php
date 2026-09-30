<?php declare(strict_types=1);
   use PHPUnit\Framework\TestCase;

   final class RemoteCase extends TestCase
   {
      public function testBasicCall(): void
      {
         $test_url = $_ENV['base_url']."/log.php";
         $request = curl_init($test_url);
         curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
         $response = curl_exec($request);

         $this->assertSame($response, "No session_id");
      }
   }

?>