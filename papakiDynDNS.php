<?
/*
 * papakiDynDNS.php
 * A script to update a specific dns record at papaki.gr free DNS Hosting service
 *
 *
 * @author: Παραστατίδης Νίκος <paranic@gmail.com>
 * @version: 1.0 (2012-05-25)
 */


define('DEBUG', TRUE);

include('dom/simple_html_dom.php');

$config['host'] = 'quad';
$config['domain'] = 'quake.gr';
$config['new_ip_address'] = '127.0.0.1';
$config['papaki_username'] = 'your_papaki_gr_username';
$config['papaki_password'] = 'your_papaki_gr_password';

// Do the login
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.papaki.gr/cp2/login.aspx?username=' . $config['papaki_username'] . '&password=' . $config['papaki_password']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookieFile.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookieFile.txt');
$response = curl_exec($ch);
curl_close($ch);
echo "Login Response: " . $response . "\n";
if ($response == 'false') die();

// Fetch domain DNS records
if (DEBUG) print_r("Fetching DNS records.\n");
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.papaki.gr/cp2/manageDNS/?domain=' . $config['domain']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookieFile.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookieFile.txt');
$response = curl_exec($ch);
curl_close($ch);

// Search for the A record we need
if (DEBUG) print_r("Searching for the needed A record.\n");
$html = new simple_html_dom();
$html->load($response);
$record_updated = FALSE;
foreach($html->find('span') as $span)
{
	// rpttypes_ctl00 are A records
	if (startsWith($span->id, 'rpttypes_ctl00_rptRecords_ctl') AND endsWith($span->id, '_lbl_name'))
	{
		if ($span->plaintext == $config['host'] . '.' . $config['domain'])
		{
			$rptRecord = explode('_', $span->id);
			$rptRecord = $rptRecord[3];

			$current_ip = $html->find('span[id=rpttypes_ctl00_rptRecords_' . $rptRecord . '_lbl_content]');
			if (DEBUG) print_r("Record found! " . $span->plaintext . " -> " . $current_ip[0]->plaintext . "\n");
			if ($config['new_ip_address'] == $current_ip[0]->plaintext)
			{
				if (DEBUG) print_r("No need to update, IP is the same as the one we are trying to update\n");
				die();
			}

			if (DEBUG) print_r("The record has to be updated.!\n");

			$search_id = 'rpttypes_ctl00_rptRecords_' . $rptRecord . '_lnk_edit';
			$edit_button = $html->find('a[id=' . $search_id . ']');
			$did = $edit_button[0]->did;
			$mode = $edit_button[0]->mode;

			// Fetch update form, so we can get VIEWSTATE and EVENTVALIDATION
			if (DEBUG) print_r("Fetching update form.\n");
			$c = curl_init();
			curl_setopt($c, CURLOPT_URL, 'https://www.papaki.gr/cp2/manageDNS/manageDNS.aspx?did=' . $did . '&mode=' . $mode);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_COOKIEFILE, 'cookieFile.txt');
			curl_setopt($c, CURLOPT_COOKIEJAR, 'cookieFile.txt');
			$response = curl_exec($c);
			curl_close($c);
			$update_html = new simple_html_dom();
			$update_html->load($response);
			$view_state = $update_html->find('input[id=__VIEWSTATE]');
			$event_validation = $update_html->find('input[id=__EVENTVALIDATION]');

			// Do the post to update form
			if (DEBUG) print_r("Posting new data.\n");
			$post_fields = array();
			$post_fields['__EVENTTARGET'] = 'btn_add';
			$post_fields['__VIEWSTATE'] = $view_state[0]->value;
			$post_fields['__EVENTVALIDATION'] = $event_validation[0]->value;
			$post_fields['txt_Host_A'] = $config['host'];
			$post_fields['txt_IP_A'] = $config['new_ip_address'];
			$post_fields['lst_ttl_A'] = '3600';
			$c = curl_init();
			curl_setopt($c, CURLOPT_URL, 'https://www.papaki.gr/cp2/manageDNS/manageDNS.aspx?did=' . $did . '&mode=' . $mode);
			curl_setopt($c, CURLOPT_POST, true);
			curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post_fields));
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_COOKIEFILE, 'cookieFile.txt');
			curl_setopt($c, CURLOPT_COOKIEJAR, 'cookieFile.txt');
			$response = curl_exec($c);
			curl_close($c);

			$record_updated = TRUE;
		}
	}
}

if ($record_updated == FALSE)
{
	// insert new record
	if (DEBUG) print_r("Record not found. I have to insert a new one but its not implemented yet.\n");
}

if (DEBUG) print_r("I think i thaw a puthyduck.\n");


// Helper functions
function startsWith($haystack, $needle)
{
	$length = strlen($needle);
	return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
	$length = strlen($needle);
	if ($length == 0)
	{
		return true;
	}

	$start  = $length * -1; //negative
	return (substr($haystack, $start) === $needle);
}

?>