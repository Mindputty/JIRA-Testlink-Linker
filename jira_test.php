<?php

/// following imports are already defined in bugAdd.php
ini_set('display_errors', 'On');
require_once '../../third_party/xml-rpc/class-IXR.php';
require_once '../api/xmlrpc/v1/api.const.inc.php';

$db_servername = "localhost";
$username = "testlinkDB_username";
$password = "DB_password";
$db_name = "testlink";
$jira_username = "JIRA_username";
$jira_pw = "JIRA_password";
$status = '';
$status_icon = '';

function post_to_jira($args){

	global $db_servername, $username, $password, $db_name, $status_icon, $status;

	$conn = new mysqli($db_servername, $username, $password, $db_name);
	if ($conn->connect_error) {
	    die("Connection failed: " . $conn->connect_error);
	}

	$issue = $args['bug_id'];
	$exec_id = $args['exec_id'];
	$bug_summary = $args['bug_summary'];
	$tproject_id = $args['tproject_id'];
	$tcversion_id = $args['tcversion_id'];
	$tc_text = $args['bug_notes'];
	$sql = "SELECT prefix FROM testprojects WHERE id = $tproject_id;";
	$res = $conn->query($sql);
	$fetched = $res->fetch_object();
	$prefix = $fetched->prefix;
	$sql = "SELECT tc_external_id FROM tcversions WHERE id = $tcversion_id;";
	$res = $conn->query($sql);
	$fetched = $res->fetch_object();
	$external_id = $fetched->tc_external_id;
	$tc_id = $prefix . "-" . $external_id;
	$conn->close();

	$exec_url = generate_execution_url($exec_id);
	echo "status is: " . $status;
	choose_status_url($status);
	post_execution_url_to_jira($issue, $exec_url, $tc_id, $tc_text);

}

function delete_from_jira($exec_id, $bug_id){

	$exec_url = generate_execution_url($exec_id);
	$exec_url_encoded = "globalId=" . urlencode("system=".$exec_url);
	delete_link_from_jira($bug_id, $exec_url_encoded);
	
}

function delete_link_from_jira($issue, $exec_url){
	global $jira_username, $jira_pw;

	$jira_base_url = "https://cenx-cf.atlassian.net";
	$remotelink_stem = sprintf("/rest/api/2/issue/%s/remotelink?%s",$issue, $exec_url);
	$remotelink_url = $jira_base_url . $remotelink_stem;
	$auth_stem = "/rest/auth/1/session";
	$auth_api = $jira_base_url . $auth_stem;

	try {

		$jira = curl_init();

		// authenticate with JIRA
		curl_setopt($jira, CURLOPT_COOKIESESSION, true);
		curl_setopt($jira, CURLOPT_COOKIEFILE, 'jira_api_cookie.txt');
		curl_setopt($jira, CURLOPT_URL, $auth_api);
		curl_setopt($jira, CURLOPT_POST, 1);
		curl_setopt($jira, CURLOPT_HTTPHEADER, array('Content-type: application/json;charset=UTF-8'));
		curl_setopt($jira, CURLOPT_POSTFIELDS, "{\"username\": \"$jira_username\", \"password\": \"$jira_pw\"}");
		curl_setopt($jira, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($jira);
		if (curl_getinfo($jira, CURLINFO_HTTP_CODE) != 200 && curl_getinfo($jira, CURLINFO_HTTP_CODE) != 201){
			throw new Exception(sprintf('Response code from JIRA while authenticating was %s',curl_getinfo($jira, CURLINFO_HTTP_CODE)));
		}
		// send DELETE request to JIRA server
		curl_setopt($jira, CURLOPT_URL, $remotelink_url);
		curl_setopt($jira, CURLOPT_CUSTOMREQUEST, "DELETE");
		$output = curl_exec($jira);
		if (curl_getinfo($jira, CURLINFO_HTTP_CODE) != 204){
			throw new Exception(sprintf('Response code from JIRA while deleting link was %s',curl_getinfo($jira, CURLINFO_HTTP_CODE)));
		}
		curl_close($jira);
		return true;
	} catch (Exception $e){
		echo sprintf("Caught Exception: %s \n", $e->getMessage());
		echo "This likely means that JIRA failed to delete the link to Testlink\n";
		curl_close($jira);
		return false;
	}
}

function choose_status_url($status){
	global $status_icon;
	switch($status){
		case "p":
			$status_icon = "https://www.drupal.org/files/issues/watchdog-ok2.png";
			break;
		case "f":
			$status_icon = "https://documentation.tricentis.com/en/910/content/tchb/images/mte_symbol_failed_small.png";
			break;
		default:
			$status_icon = "https://documentation.tricentis.com/en/910/content/tchb/images/mte_symbol_failed_small.png";
	}
}

function post_execution_url_to_jira($issue, $exec_url, $tc_id, $tc_text){

	global $jira_username, $jira_pw, $status_icon;

	$jira_base_url = "https://companyname.atlassian.net";
	$remotelink_stem = sprintf("/rest/api/2/issue/%s/remotelink",$issue);
	$remotelink_url = $jira_base_url . $remotelink_stem;
	$auth_stem = "/rest/auth/1/session";
	$auth_api = $jira_base_url . $auth_stem;

	try {

		$jira = curl_init();

		// authenticate with JIRA
		curl_setopt($jira, CURLOPT_COOKIESESSION, true);
		curl_setopt($jira, CURLOPT_COOKIEFILE, 'jira_api_cookie.txt');
		curl_setopt($jira, CURLOPT_URL, $auth_api);
		curl_setopt($jira, CURLOPT_POST, 1);
		curl_setopt($jira, CURLOPT_HTTPHEADER, array('Content-type: application/json;charset=UTF-8'));
		curl_setopt($jira, CURLOPT_POSTFIELDS, "{\"username\": \"$jira_username\", \"password\": \"$jira_pw\"}");
		curl_setopt($jira, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($jira);
		if (curl_getinfo($jira, CURLINFO_HTTP_CODE) != 200 && curl_getinfo($jira, CURLINFO_HTTP_CODE) != 201){
			throw new Exception(sprintf('Response code from JIRA while authenticating was %s',curl_getinfo($jira, CURLINFO_HTTP_CODE)));
		}
		// add testlink execution link to JIRA issue
		curl_setopt($jira, CURLOPT_URL, $remotelink_url);
		curl_setopt($jira, CURLOPT_POST, 1);
		curl_setopt($jira, CURLOPT_POSTFIELDS, "{\"globalId\" : \"system=$exec_url\",
												\"relationship\" : \"links to\",
												\"object\": {\"url\": \"$exec_url\",
											        		 \"title\": \"$tc_id\",
		 											         \"summary\": \"$tc_text\",
		 											         \"icon\" : {\"url16x16\" : \"http://marketing.dell.com/Templates/ion/Dell_US_Brand_Site/themes/Dell/tc-icon.jpg\",
		 											     				 \"title\" : \"Testlink Test Case Execution\"},
											        		 \"status\": {\"resolved\": false,
											        		  			  \"icon\": {\"url16x16\": \"$status_icon\",
											        		  			  			 \"title\": \"Status\",
											        		  			  			 \"link\" : \"$exec_url\"}}}}");

		$output = curl_exec($jira);
		if(curl_getinfo($jira, CURLINFO_HTTP_CODE) != 200 && curl_getinfo($jira, CURLINFO_HTTP_CODE) != 201){
			throw new Exception(sprintf('Response code from JIRA while adding link was %s',curl_getinfo($jira, CURLINFO_HTTP_CODE)));	
		}
		curl_close($jira);
		return true;
	} catch (Exception $e){
		echo sprintf("Caught Exception: %s \n", $e->getMessage());
		echo "This likely means that JIRA failed to link back to Testlink\n";
		curl_close($jira);
		return false;
	}
}

function generate_execution_url($exec_id){

	global $db_servername, $username, $password, $db_name, $status;

	$conn = new mysqli($db_servername, $username, $password, $db_name);
	if ($conn->connect_error) {
	    die("Connection failed: " . $conn->connect_error);
	}

	$sql = "SELECT build_id, tcversion_id, status FROM executions WHERE id = $exec_id";
	$res = $conn->query($sql);
	$fetched = $res->fetch_object();	
	$tcversion_id = $fetched->tcversion_id;
	$build_id = $fetched->build_id;
	$status = $fetched->status;
	$sql = "SELECT id FROM testplan_tcversions WHERE tcversion_id = $tcversion_id;";
	$res = $conn->query($sql);
	$fetched = $res->fetch_object();
	$feature_id = $fetched->id;
	$conn->close();

	$exec_link = "http://testlink.cenx.localnet/testlink/ltx.php?item=exec&feature_id=$feature_id&build_id=$build_id";
	return $exec_link;
}

?>
