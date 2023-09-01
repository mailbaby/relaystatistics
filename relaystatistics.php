<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function relaystatistics_config() {
    return array(
        "name" => "MailBaby Statistics",
        "description" => "This module allows you to view Mail.baby statistics, manage rules and blocks within WHMCS.",
        "version" => "1.0",
        "author" => "Mail.Baby",
        "language" => "english",
        "fields" => array(
            "api_email" => array("FriendlyName" => "API Email", "Type" => "text", "Size" => "25", "Description" => "Enter your email here.", "Default" => "", "Required" => true,),
            "api_key" => array("FriendlyName" => "API Key", "Type" => "password", "Size" => "25", "Description" => "Enter your API key here.", "Default" => "", "Required" => true,),
            "wsdl_url" => array("FriendlyName" => "WSDL URL", "Type" => "text", "Size" => "50", "Description" => "Enter the SOAP WSDL URL for the Mail.baby API", "Default" => "https://my.interserver.net/api.php?wsdl", "Required" => true,),
        )
    );
}

function relaystatistics_output($vars) {
    if(isset($_GET['pg'])) {
        switch($_GET['pg']) {
            case 'viewlogs':
            case 'searchlogs':
			case 'viewblocks':
			case 'viewrules':
                return relaystatistics_dispatch($vars);
    }
	}
    $api_email = $vars['api_email'];
    $api_key = $vars['api_key'];
    $wsdl_url = $vars['wsdl_url'];
    $client = new SoapClient($wsdl_url, array('cache_wsdl' => WSDL_CACHE_NONE));

    try  { 
        $res = $client->api_login($api_email, $api_key);
        if (!empty($res)) {
            $_SESSION['sid'] = $res;
            $res = $client->api_mail_get_services($_SESSION['sid']);

            echo "<table class='table table-striped'>";
            echo "<thead class='thead-dark'>
                  <tr>
                  <th>Account ID</th>
                  <th>Username</th>
                  <th>Order Date</th>
                  <th>Status</th>
                  </tr>
                  </thead>";
            echo "<tbody>";
foreach($res as $service) {
    if($service->mail_status == 'active') {
        echo "<tr class='table-success'>
                <td>{$service->mail_id}</td>
                <td>{$service->mail_username}</td>
                <td>{$service->mail_order_date}</td>
                <td>{$service->mail_status}</td>
                <td>
                    <div class='btn-group' role='group'>
                        <button class='btn btn-primary' style='margin-right:10px;' onclick='window.location.href=\"addonmodules.php?module=relaystatistics&pg=viewlogs&mail_id={$service->mail_id}\"'>View Logs</button>
                        <button class='btn btn-primary' style='margin-right:10px;' onclick='window.location.href=\"addonmodules.php?module=relaystatistics&pg=searchlogs&mail_id={$service->mail_id}\"'>Search Logs</button>
						<button class='btn btn-primary' style='margin-right:10px;' onclick='window.location.href=\"addonmodules.php?module=relaystatistics&pg=viewblocks&mail_id={$service->mail_id}\"'>Manage Blocks</button>
						<button class='btn btn-primary' style='margin-right:10px;' onclick='window.location.href=\"addonmodules.php?module=relaystatistics&pg=viewrules&mail_id={$service->mail_id}\"'>Manage Rules</button>
                    </div>
                </td>
              </tr>";
    } else {
        echo "<tr>
                <td>{$service->mail_id}</td>
                <td>{$service->mail_username}</td>
                <td>{$service->mail_order_date}</td>
                <td>{$service->mail_status}</td>
                <td></td>
              </tr>";
    }
}
            echo "</tbody></table>";
        } else {
            echo "Invalid API response";
            echo "<pre>";
            print_r($res);
            echo "</pre>";
        }
    } catch (SoapFault $fault) {
        trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
    }
}

function relaystatistics_viewlogs($vars) {
    $apiUrl = "https://api.mailbaby.net/mail/log?limit=1000";
    
    $apiKey = Capsule::table('tbladdonmodules')->where('module', 'relaystatistics')->where('setting', 'api_key')->value('value');

    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $apiUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "X-API-KEY: " . $apiKey
      ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $response_array = json_decode($response, true);

        if(!empty($response_array)){
            echo "<table id='emailLogTable' class='table table-striped'>";
            echo "<thead class='thead-dark'>
                  <tr>
                  <th>ID</th>
                  <th>From</th>
                  <th>To</th>
                  <th>Subject</th>
                  <th>Message ID</th>
                  <th>Created</th>
                  <th>User</th>
                  <th>Origin IP</th>
                  <th>Interface</th>
                  <th>Sending Zone</th>
                  <th>Response</th>
                  </tr>
                  </thead>";
            echo "<tbody>";
            foreach($response_array['emails'] as $email) {
                echo "<tr>
                        <td>{$email['id']}</td>
                        <td>{$email['from']}</td>
                        <td>{$email['to']}</td>
                        <td>{$email['subject']}</td>
                        <td>{$email['messageId']}</td>
                        <td>{$email['created']}</td>
                        <td>{$email['user']}</td>
                        <td>{$email['origin']}</td>
                        <td>{$email['interface']}</td>
                        <td>{$email['sendingZone']}</td>
                        <td>{$email['response']}</td>
                      </tr>";
            }
            echo "</tbody></table>";
            echo "<script src='https://code.jquery.com/jquery-3.5.1.js'></script>";
            echo "<script src='https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js'></script>";
            echo "<script src='https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js'></script>";
            echo "<link rel='stylesheet' href='https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css'>";
            echo "<script>
                    $(document).ready(function() {
                        $('#emailLogTable').DataTable();
                    });
                  </script>";
        } else {
            echo "No email logs found.";
        }
    }
}

function relaystatistics_viewblocks($vars) {
    $apiKey = Capsule::table('tbladdonmodules')->where('module', 'relaystatistics')->where('setting', 'api_key')->value('value');

    // Check for delete email request
    if (isset($_GET['delete_email'])) {
        $emailToDelete = $_GET['delete_email'];

        $deleteUrl = "https://api.mailbaby.net/mail/blocks/delete";
        $curlDelete = curl_init();

        curl_setopt_array($curlDelete, [
            CURLOPT_URL => $deleteUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(['email' => $emailToDelete]),
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json",
                "X-API-KEY: " . $apiKey
            ],
        ]);

        $deleteResponse = curl_exec($curlDelete);
        $deleteErr = curl_error($curlDelete);

        curl_close($curlDelete);

        if ($deleteErr) {
            echo "Error deleting email: " . $deleteErr . "<br/>";
        } else {
            $deleteData = json_decode($deleteResponse, true);
            if ($deleteData['status'] === 'ok') {
                echo "Successfully deleted: " . $emailToDelete . "<br/>";
            } else {
                echo "Failed to delete: " . $emailToDelete . ". Reason: " . $deleteData['text'] . "<br/>";
            }
        }
    }

    // Fetch the block list
    $apiUrl = "https://api.mailbaby.net/mail/blocks";
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "X-API-KEY: " . $apiKey
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $blocks = json_decode($response, true);

        if (!empty($blocks)) {
            echo "<h4>Local Blocks</h4>";
            displayBlockTable($blocks['local']);

            echo "<h4>MBTrap Blocks</h4>";
            displayBlockTable($blocks['mbtrap']);

            echo "<h4>Subject Blocks</h4>";
            displaySubjectBlockTable($blocks['subject']);
        } else {
            echo "No blocked emails found.";
        }
    }
	
}


function displayBlockTable($blockArray) {
    // Get existing URL without any parameters
    $baseUrl = strtok($_SERVER["REQUEST_URI"], '?');

    // Construct the delete URL based on existing parameters
    $deleteUrlBase = $baseUrl . "?module=relaystatistics&pg=viewblocks&mail_id={$_GET['mail_id']}&delete_email=";

    echo "<table class='table table-striped'>";
    echo "<thead class='thead-dark'>
            <tr>
                <th>Date</th>
                <th>From</th>
                <th>To</th>
                <th>Subject</th>
                <th>Message ID</th>
            </tr>
          </thead>";
    echo "<tbody>";
    foreach($blockArray as $block) {
        echo "<tr>
                <td>{$block['date']}</td>
                <td><a href='{$deleteUrlBase}{$block['from']}'>{$block['from']}</a></td>
                <td>{$block['to']}</td>
                <td>{$block['subject']}</td>
                <td>{$block['messageId']}</td>
              </tr>";
    }
    echo "</tbody></table>";
}

function deleteEmailBlock($email) {
    $apiUrl = "https://api.mailbaby.net/mail/blocks/delete";
    $apiKey = Capsule::table('tbladdonmodules')->where('module', 'relaystatistics')->where('setting', 'api_key')->value('value');

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['email' => $email]),
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Content-Type: application/json",
            "X-API-KEY: " . $apiKey
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return ["status" => "error", "message" => "cURL Error #:" . $err];
    } else {
        $responseData = json_decode($response, true);
        if ($responseData['status'] == 'ok') {
            return ["status" => "success", "message" => "Successfully deleted: {$email}"];
        } else {
            return ["status" => "error", "message" => "Failed to delete: {$email}. API Response: " . $responseData['text']];
        }
    }
}


function displaySubjectBlockTable($blockArray) {
    echo "<table class='table table-striped'>";
    echo "<thead class='thead-dark'>
            <tr>
                <th>From</th>
                <th>Subject</th>
            </tr>
          </thead>";
    echo "<tbody>";
    foreach($blockArray as $block) {
        echo "<tr>
                <td>{$block['from']}</td>
                <td>{$block['subject']}</td>
              </tr>";
    }
    echo "</tbody></table>";
}



function relaystatistics_searchlogs($vars) {
    echo "<form id='searchForm' action='' method='post' class='mb-5'>
          <div class='form-row'>
            <div class='form-group col-md-6'>
              <label for='startDate'>Start Date</label>
              <input type='text' class='form-control datepicker' id='startDate' name='startDate'>
            </div>
            <div class='form-group col-md-6'>
              <label for='endDate'>End Date</label>
              <input type='text' class='form-control datepicker' id='endDate' name='endDate'>
            </div>
<script>
    document.getElementById('searchForm').addEventListener('submit', function(e) {
        e.preventDefault();

        let startDateInput = document.getElementById('startDate');
        if(startDateInput.value) {
            let startDate = new Date(startDateInput.value);
            startDateInput.value = startDate.getTime() / 1000; // convert to Unix timestamp
        }

        let endDateInput = document.getElementById('endDate');
        if(endDateInput.value) {
            let endDate = new Date(endDateInput.value);
            endDateInput.value = endDate.getTime() / 1000; // convert to Unix timestamp
        }

        this.submit();  // Submit the modified form
    });
</script>

          </div>
          <div class='form-row'>
            <div class='form-group col-md-6'>
              <label for='from'>From</label>
              <input type='text' class='form-control' id='from' name='from'>
            </div>
            <div class='form-group col-md-6'>
              <label for='to'>To</label>
              <input type='text' class='form-control' id='to' name='to'>
            </div>
          </div>
          <div class='form-row'>
            <div class='form-group col-md-6'>
              <label for='id'>MailBaby Login</label>
              <input type='text' class='form-control' id='id' name='id'>
            </div>
            <div class='form-group col-md-6'>
              <label for='limit'>Limit of Records</label>
              <input type='number' class='form-control' id='limit' name='limit' value='100'>
            </div>
          </div>
          <div class='form-row'>
            <div class='form-group col-md-6'>
              <label for='mailid'>Mail ID</label>
              <input type='text' class='form-control' id='mailid' name='mailid'>
            </div>
            <div class='form-group col-md-6'>
              <label for='origin'>Origin IP</label>
              <input type='text' class='form-control' id='origin' name='origin'>
            </div>
          </div>
          <div class='form-group'>
            <label for='subject'>Subject</label>
            <input type='text' class='form-control' id='subject' name='subject'>
          </div>
          <button type='submit' class='btn btn-primary btn-lg btn-block'>Search</button>
          </form>
		  </br>";
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $apiUrl = "https://api.mailbaby.net/mail/log?";
        
        // Add the parameters to the API url if they are set
        foreach ($_POST as $key => $value) {
            if (!empty($value)) {
                $apiUrl .= "$key=$value&";
            }
        }

        $apiUrl = rtrim($apiUrl, "&");  // Remove the trailing '&'

        $apiKey = Capsule::table('tbladdonmodules')->where('module', 'relaystatistics')->where('setting', 'api_key')->value('value');

        $curl = curl_init();
        curl_setopt_array($curl, [
          CURLOPT_URL => $apiUrl,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "X-API-KEY: " . $apiKey
          ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            $response_array = json_decode($response, true);

            if(!empty($response_array)){
                // Render the table
                echo "
                <table class='table table-striped' id='searchResultTable'>
                    <thead class='thead-dark'>
                        <tr>
                            <th>ID</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Subject</th>
                            <th>Created</th>
                            <th>MailBaby Login</th>
                            <th>Origin IP</th>
                            <th>Sending Zone</th>
							<th>Queued</th>
                            <th>Response</th>
                        </tr>
                    </thead>
					
                    <tbody>";
                foreach($response_array['emails'] as $email) {
                    echo "
                    <tr>
                        <td>{$email['id']}</td>
                        <td>{$email['from']}</td>
                        <td>{$email['to']}</td>
                        <td>{$email['subject']}</td>
                        <td>{$email['created']}</td>
                        <td>{$email['user']}</td>
                        <td>{$email['origin']}</td>
                        <td>{$email['sendingZone']}</td>
						<td>{$email['queued']}</td>
                        <td>{$email['response']}</td>
                    </tr>";
                }
                echo "
                    </tbody>
                </table>";
            } else {
                echo "No email logs found.";
            }
        }
    }

    echo "<script>
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true
        });

        $(document).ready( function () {
            $('#searchResultTable').DataTable();
        });
        </script>";
}

function relaystatistics_viewrules($vars) {
    $apiKey = Capsule::table('tbladdonmodules')->where('module', 'relaystatistics')->where('setting', 'api_key')->value('value');
    
    // Delete rule
    if (isset($_GET['delete_rule_id'])) {
        $ruleId = $_GET['delete_rule_id'];
        deleteRule($ruleId, $apiKey);
    }
    
    // Add rule
    if (isset($_POST['add_rule'])) {
        $user = $_POST['user'];
        $type = $_POST['type'];
        $data = $_POST['data'];
        addRule($user, $type, $data, $apiKey);
    }

    // Display add rule form
    displayAddRuleForm();
    
    // Fetch the deny block rules
    $apiUrl = "https://api.mailbaby.net/mail/rules";
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "X-API-KEY: " . $apiKey
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $rules = json_decode($response, true);
        if (!empty($rules)) {
            echo "<h4>Deny Block Rules</h4>";
            displayRuleTable($rules);
        } else {
            echo "No deny block rules found.";
        }
    }
}

function displayAddRuleForm() {
    echo <<<HTML
    <div class="container my-4">
        <h4>Add Deny Block Rule</h4>
        <form method="POST" action="">
            <div class="form-group">
                <label for="user">User (optional):</label>
                <input type="text" class="form-control" id="user" name="user">
            </div>
            <div class="form-group">
                <label for="type">Type:</label>
                <select class="form-control" id="type" name="type">
                    <option value="domain">Sender Domain</option>
                    <option value="email">Sender Email</option>
                    <option value="startswith">Sender Email Starts With</option>
					<option value="destination">Destination Email</option>
                </select>
            </div>
            <div class="form-group">
                <label for="data">Data:</label>
                <input type="text" class="form-control" id="data" name="data">
            </div>
            <button type="submit" name="add_rule" class="btn btn-primary">Add Rule</button>
        </form>
    </div>
HTML;
}

function displayRuleTable($ruleArray) {
    $currentModuleUrl = "addonmodules.php?module=relaystatistics&pg=viewrules";
    echo <<<HTML
    <div class="container my-4">
        <h4>Deny Block Rules</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Data</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
HTML;
    foreach($ruleArray as $rule) {
        echo <<<HTML
                <tr>
                    <td>{$rule['id']}</td>
                    <td>{$rule['user']}</td>
                    <td>{$rule['type']}</td>
                    <td>{$rule['data']}</td>
                    <td>{$rule['created']}</td>
                    <td><a href="{$currentModuleUrl}&delete_rule_id={$rule['id']}" class="btn btn-danger btn-sm">Delete</a></td>
                </tr>
HTML;
    }
    echo <<<HTML
            </tbody>
        </table>
    </div>
HTML;
}



function addRule($user, $type, $data, $apiKey) {
    $apiUrl = "https://api.mailbaby.net/mail/rules";
    $postFields = [
        'user' => $user,
        'type' => $type,
        'data' => $data
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($postFields),
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Content-Type: application/json",
            "X-API-KEY: " . $apiKey
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo "Error adding rule: " . $err;
    } else {
        $responseData = json_decode($response, true);
        if ($responseData['status'] === 'ok') {
            echo "Rule added successfully.";
        } else {
            echo "Failed to add rule. " . $responseData['text'];
        }
    }
}

function deleteRule($ruleId, $apiKey) {
    $apiUrl = "https://api.mailbaby.net/mail/rules/{$ruleId}"; // Assuming ruleId can be used directly in the URL for deletion
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "DELETE", // Assuming DELETE HTTP method for deleting rules
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "X-API-KEY: " . $apiKey
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo "Error deleting rule: " . $err;
    } else {
        $responseData = json_decode($response, true);
        if ($responseData['status'] === 'ok') {
            echo "Rule deleted successfully.";
        } else {
            echo "Failed to delete rule. " . $responseData['text'];
        }
    }
}


function relaystatistics_dispatch($vars) {
    $pg = isset($_REQUEST['pg']) ? $_REQUEST['pg'] : '';

    switch ($pg) {
        case 'viewlogs':
            relaystatistics_viewlogs($vars);
            break;
        case 'searchlogs':
            relaystatistics_searchlogs($vars);
            break;
		case 'viewblocks':
            relaystatistics_viewblocks($vars);
            break;
		case 'viewrules':
            relaystatistics_viewrules($vars);
            break;	
        default:
            relaystatistics_output($vars);
            break;
    }
}




function relaystatistics_api_login() {
    $wsdlUrl = Capsule::table('tbladdonmodules')->where('module', 'relaystatistics')->where('setting', 'wsdl_url')->value('value');
    $apiEmail = Capsule::table('tbladdonmodules')->where('module', 'relaystatistics')->where('setting', 'api_email')->value('value');
    $apiKey = Capsule::table('tbladdonmodules')->where('module', 'relaystatistics')->where('setting', 'api_key')->value('value');

    $client = new SoapClient($wsdlUrl, array('cache_wsdl' => WSDL_CACHE_NONE));
    try {
        $res = $client->api_login($apiEmail, $apiKey);
        if (isset($res->sid)) {
            $_SESSION['sid'] = $res->sid;
            return $res->sid;
        } else {
            throw new Exception('Invalid API response');
        }
    } catch (Exception $ex) {
        echo "Exception Occurred!\n";
        echo "Code:{$ex->getCode()}\n";
        echo "Message:{$ex->getMessage()}\n";
        return false;
    }
}

function relaystatistics_api_mail_get_services() {
    $wsdlUrl = Capsule::table('tbladdonmodules')->where('module', 'relaystatistics')->where('setting', 'wsdl_url')->value('value');

    $client = new SoapClient($wsdlUrl, array('cache_wsdl' => WSDL_CACHE_NONE));
    try {
        if (!isset($_SESSION['sid'])) {
            throw new Exception('No session ID available. Please login first.');
        }
        $res = $client->api_mail_get_services($_SESSION['sid']);
        return $res;
    } catch (Exception $ex) {
        echo "Exception Occurred!\n";
        echo "Code:{$ex->getCode()}\n";
        echo "Message:{$ex->getMessage()}\n";
        return false;
    }
}

