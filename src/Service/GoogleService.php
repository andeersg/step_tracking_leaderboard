<?php

namespace App\Service;

use Google\Client;
use Google_Service_Fitness;

class GoogleService {

  public function getClient()
  {
    $redirect_uri = $_ENV["GOOGLE_REDIRECT_URI"];

    $client = new Client();
    $client->setApplicationName("Step Tracker");
    $client->setAuthConfig(__DIR__ . '/../../client_secret.json');
    $client->addScope(Google_Service_Fitness::FITNESS_ACTIVITY_READ);
    $client->addScope("email");
    $client->setAccessType("offline");
    $client->setRedirectUri($redirect_uri);
    $client->setIncludeGrantedScopes(true);

    return $client;
  }

  public function calculateTimestamps($daysBack = 1)
  {
      $start = strtotime(date('o-m-d 00:00:00')) - (3600 * 24 * $daysBack);
      $end = $start + (3600 * 24);
    
      return [
        'start' => $start * 1000,
        'end' => $end * 1000,
      ];
  }

  public function processAggregatedData($raw_data)
  {
    $result = [];

    foreach ($raw_data['bucket'] as $day) {
      $start = $day['startTimeMillis'];
      $steps = 0;
      if (isset($day['dataset'][0]) && isset($day['dataset'][0]['point'][0]) && isset($day['dataset'][0]['point'][0]['value'][0]['intVal'])) {
        $steps = $day['dataset'][0]['point'][0]['value'][0]['intVal'];
      }
      $result[$start] = $steps;
    }

    return $result;
  }

}