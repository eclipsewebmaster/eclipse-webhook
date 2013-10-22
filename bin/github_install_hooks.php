<?php
/*
 * command line webhook installer for gitub repos
 */

if (file_exists('../config/projects_local.php')) {
  include('../config/projects_local.php');
} else {
  include('../config/projects.php');
}
include('../lib/restclient.php');

$client = new RestClient(GITHUB_ENDPOINT_URL);

if (!defined('GITHUB_TOKEN')) {
  exit('You must provide a Github access token environment variable to install webhooks.');
}

//search for command line passed github organization to monitor
$options = getopt("o::", array('organization::'));
if ($options['organization']) {
  $github_organization = $options['organization'];
}
if ($options['o']) {
  $github_organization = $options['o'];
}
if ($github_organization == '') {
  exit("You must provide a Github organization as a target for webhook installation in the configuration file, or on the command line as -o=[name] or --organization=[name]\n");
}

if (!count($github_projects)) {
  //if the projects list is empty, enumerate all organization repos
  error_log('no github repositories listed in config/projects.php, enumerating all repos.');
  $url = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'orgs',
    $github_organization,
    'repos'
  ));

  $result = $client->get($url);
  if (is_array($result)) {
    for ($i=0; $i < count($result); $i++) { 
      $github_projects[] = $result[$i]->name;
    }
    error_log('repo list: '. print_r($github_projects, true));
  }
}

//create payload required for github hook post
//see http://developer.github.com/v3/repos/hooks/#create-a-hook
$payload = null;
$payload->name = 'web';
$payload->active = true;
$payload->events = $github_hook_add_events;
$payload->config = null;
$payload->config->content_type = "form";
$payload->config->url = WEBHOOK_SERVICE_URL;

echo('Installing '. count($github_projects). " web hooks\n");

//iterate over repos list, posting payload via curl
//TODO: test for existing hook and verify configuration
for ($i=0; $i < count($github_projects); $i++) { 

  $patchUrl = implode('/', array(
    GITHUB_ENDPOINT_URL,
    'repos',
    $github_organization,
    $github_projects[$i],
    'hooks'
  ));

  $resultObj = $client->post($patchUrl, $payload);
  
  //report success or error
  if ($resultObj) {
    if ($resultObj->url) {
      echo('installed webhook: ' . $resultObj->url . "\n");      
    } else {
      if ($resultObj->message == "Validation Failed") {
        echo("not installed -- hook for " . $github_projects[$i] . " is already present\n");
      } else {
        echo('encountered github api error: ' . $resultObj->message . "\n");
      }
    }
  } else {
    echo('encountered an error processing ' . $patchUrl . "\n" );
  }
}

/* utility function to perform a POST operation via curl */
function curl_post($url, $post = NULL, array $options = array()) { 
  $defaults = array( 
    CURLOPT_POST => 1, 
    CURLOPT_HEADER => 0, 
    CURLOPT_HTTPHEADER => array("Authorization: token ".GITHUB_TOKEN),
    CURLOPT_URL => $url, 
    CURLOPT_FRESH_CONNECT => 1, 
    CURLOPT_RETURNTRANSFER => 1, 
    CURLOPT_FORBID_REUSE => 1, 
    CURLOPT_TIMEOUT => 4, 
    CURLOPT_POSTFIELDS => $post
  ); 

  $ch = curl_init(); 
  curl_setopt_array($ch, ($options + $defaults)); 
  if( ! $result = curl_exec($ch)) 
  { 
    trigger_error(curl_error($ch)); 
  } 
  curl_close($ch); 
  return $result; 
}
  
/* utility function to perform a PATCH operation via curl */
function curl_patch($url, $post = NULL, array $options = array()) { 
  $defaults = array( 
    CURLOPT_POST => 1, 
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_HEADER => 0, 
    CURLOPT_HTTPHEADER => array("Authorization: token ".GITHUB_TOKEN),
    CURLOPT_URL => $url, 
    CURLOPT_FRESH_CONNECT => 1, 
    CURLOPT_RETURNTRANSFER => 1, 
    CURLOPT_FORBID_REUSE => 1, 
    CURLOPT_TIMEOUT => 4, 
    CURLOPT_POSTFIELDS => $post
  ); 

  $ch = curl_init(); 
  curl_setopt_array($ch, ($options + $defaults)); 
  if( ! $result = curl_exec($ch)) 
  { 
    trigger_error(curl_error($ch)); 
  } 
  curl_close($ch); 
  return $result; 
}
?>