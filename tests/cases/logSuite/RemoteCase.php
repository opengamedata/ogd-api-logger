<?php declare(strict_types=1);
   use PHPUnit\Framework\TestCase;

   require "config/t_config.php";

   final class RemoteCase extends TestCase
   {
      public function testBasicCall(): void
      {
         $request = curl_init($base_url."/log.php");
         curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
         $response = curl_exec($request);

         $this->assertSame($response, "No session_id");
      }
   }

?>