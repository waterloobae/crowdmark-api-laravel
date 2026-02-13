<?php

namespace Waterloobae\CrowdmarkDashboard;

use Exception;

class API
{
    protected string $url = 'https://app.crowdmark.com/';
    protected string $api_key_string;

    protected object $logger;
    // $this->exec uses and returns
    protected object $api_response;
    // $this->multExec uses and returns
    protected array $api_responses = [];

    protected int $max_retries = 5;
    protected int $current_try = 0;

    protected int $httpCode;

    public function __construct( object $logger )    
    {
        // constructor
        $this->logger = $logger;
        $this->buildApiKeyString();

    }

    public function buildApiKeyString()
    {
        $apiKeyFile = __DIR__ . '/../config/API_KEY.php';
        if (!file_exists($apiKeyFile)) {
            die("error: API key file does not exist, " . $apiKeyFile .". Please create one by copying API_KEY_Example.php to API_KEY.php.");
        }
        
        require $apiKeyFile;
        
        if (!isset($api_key)) {
            die("error: API key is not set correctly in ". $apiKeyFile . ". Please set the API key, \$api_key, in the API_KEY.php file.");
        }
        
        $this->api_key_string = 'api_key=' . $api_key;
        
    }

    public function exec(string $end_point){
        //Status Message
        $this->logger->setInfo("API call to " . $end_point . " started.");
        //$this->logger->echoMessage("info", "API call to " . $end_point . " started.");
        $curl = curl_init();
        // Does end_point have a ? in it?
        if (strpos($end_point, '?') !== false) {
           curl_setopt($curl, CURLOPT_URL, $this->url . $end_point . '&' . $this->api_key_string);
        } else {
           curl_setopt($curl, CURLOPT_URL, $this->url . $end_point . '?' . $this->api_key_string);
        }

       curl_setopt($curl, CURLOPT_TIMEOUT, 6000);
       curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
       
       set_time_limit(3000);

       do {
           $response = curl_exec($curl);
           if (json_decode($response) === null) {
               $this->current_try++;
                $this->consoleLog("Attempt $this->current_try failed. Retrying...");

               sleep(1); // Optional: wait 1 seconds before retrying
           }
       } while ( (json_decode($response) === null ||
                    array_key_exists('errors',json_decode($response, true)))
                    && $this->current_try < $this->max_retries);
       curl_close($curl);
       $this->current_try = 0;

       $this->httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if( json_decode($response) !== null && 
            !array_key_exists('errors',json_decode($response, true)) &&
            $this->httpCode == 200){
                $this->api_response = json_decode($response);
        }else{
            throw new Exception("API call returned non JSON response or Errors were returned from Crowdmark.");
        }

    }

    public function multiExec(array $big_end_points){
        ini_set('memory_limit', '4096M');
        $batch_size = 10;
        $interval = 0;

        $batches = array_chunk($big_end_points, $batch_size);
        // Give the server a break
        sleep($interval);
        foreach ($batches as $i => $end_points) {
            // Initialize the multi cURL handler
            $mh = curl_multi_init();
            // Array to hold individual cURL handles
            $curlHandles = [];

            // Loop through each URL and create a cURL handle for it
            foreach ($end_points as $end_point) {
                //Status Message
                $this->logger->setInfo("API call to " . $end_point . " started.");                
                //$this->logger->echoMessage("info", "API call to " . $end_point . " started.");
                $ch = curl_init();
                if (strpos($end_point, '?') !== false) {
                    curl_setopt($ch, CURLOPT_URL, $this->url . $end_point . '&' . $this->api_key_string);
                } else {
                    curl_setopt($ch, CURLOPT_URL, $this->url . $end_point . '?' . $this->api_key_string);
                }
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                // Add the handle to the multi cURL handler
                curl_multi_add_handle($mh, $ch);

                // Save the handle for later reference
                $curlHandles[] = $ch;
            }

            // Execute the multi cURL handles
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);

            // Collect the results
            foreach ($curlHandles as $j => $ch) {
                // Get the response content
                $response = curl_multi_getcontent($ch);
                if(json_decode($response) !== null){
                    // $data = json_decode($response)->data;
                    $data = json_decode($response);                    
                    $this->api_responses[ $i * $batch_size + $j] = $data;
                    // echo"Response: " . $i * $batch_size + $j . "<br>";
                }
                // Remove the handle from the multi cURL handler
                curl_multi_remove_handle($mh, $ch);

                // Close the individual cURL handle
                curl_close($ch);
            }

            // Close the multi cURL handler
            curl_multi_close($mh);

            // Wait for the interval before starting the next batch
            sleep($interval);
        }
    }
    

    public function getResponse()
    {
        return $this->api_response;
    }
    public function getResponses()
    {
        return $this->api_responses;
    }
    public function getHttpCode()
    {
        return $this->httpCode;
    }
    public function consoleLog($msg){
        $output = $msg;
        if (is_array($output))
            $output = implode(',', $output);
        echo "<script>console.log('Error message(s): " . $output . "' );</script>";
    }

}