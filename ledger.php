<?php
include 'ledgerManager.php';
// Credit to https://github.com/adamtomecek/Template
include 'templateManager/Template.php';

$pathArray = pathinfo(__FILE__);
$path = preg_replace('/\/var\/www\//', '', $pathArray['dirname']). "/";
$thisScript = $pathArray['basename'];
$host = $_SERVER['HTTP_HOST'];
list($user, $password, $database, $table) = setupEnvironment();
$ledgerManager = new LedgerManager($user, $password, $database, $table);

$verb = getRequestParam('verb', 'transactions');
$id = getRequestParam('id');
$description = getRequestParam('description');
$amount = getRequestParam('amount');
$credit = getRequestParam('credit');
$cleared = getRequestParam('cleared', 0);
$duration = getRequestParam('duration', 15);
$windowStartDate = date('Y-m-d', strtotime("-". $duration. " days"));

if (isset($verb)) {
	if ($verb == 'update' &&
			$ledgerManager->validateUpdate($id, $description, $amount, $cleared)) {
		$ledgerManager->updateTransaction($id, $description, $amount, $cleared);
		error_log('updated transaction: '. $id);
		redirectTo("{$host}/{$path}{$thisScript}");
	}
	elseif ($verb == 'create' && $ledgerManager->validateCreate($description, $amount, $credit)) {
		$ledgerManager->insertTransaction($description, $amount, $credit);
		error_log('inserted transaction');
		redirectTo("{$host}/{$path}{$thisScript}");
	}
	elseif ($verb == 'delete' && $ledgerManager->validateDelete($id)) {
		$ledgerManager->deleteTransaction($id);
		error_log('deleted transaction: '. $id);
		redirectTo("{$host}/{$path}{$thisScript}");
	}
	elseif ($verb == 'summary') {
		list($header, $transactionGroups) = setupSummary($thisScript, $table, $duration, $ledgerManager);
		//echo json_encode($transactionGroups). "<br>";
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->totalBalance = $ledgerManager->getBalance();
		$template->daysLeft = $ledgerManager->numberOfDaysLeftInPayPeriod();
		$template->header = $header;
		$template->transactionGroups = $transactionGroups;
		$template->setFile('summary.phtml')
			->setLayout('@layout.phtml')
			->render();
	}
	elseif ($verb == 'transactions') {
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->totalBalance = $ledgerManager->getBalance();
		$template->daysLeft = $ledgerManager->numberOfDaysLeftInPayPeriod();
		$template->unclearedAmount = sprintf('%01.2f', $ledgerManager->getUnclearedAmount());
		$template->transactions = $ledgerManager->retrieveARangeOfTransactionsAsObjects($windowStartDate);
		$template->unclearedTransactions = $ledgerManager->getAllUnclearedTransactionsOutsideCurrentWindowAsObjects($windowStartDate);
		$template->setFile('transactions.phtml')
			->setLayout('@layout.phtml')
			->render();
	}
	else {
		echo '(nope)';
	}
}

function setupEnvironment() {
	try {
		$environmentFile = file_get_contents('env.setup');
	} catch (Exception $e) {
		error_log('Mysql creds not determined, exiting');
		exit(1);
	}
	return explode("\n", $environmentFile);
}
function getRequestParam($field, $default = null) {
	return isset($_REQUEST[$field]) ? $_REQUEST[$field] : $default;
}
function redirectTo($URL) {
	header( "HTTP/1.1 301 Moved Permanently" );
	header( "Status: 301 Moved Permanently" );
	header( "Location: https://{$URL}");
	exit(0); // This is Optional but suggested, to avoid any accidental output
}
function setupSummary($thisScript, $table, $duration, $ledgerManager) {
	$lnks = [
		'Weekly' => 7,
		'Bi-Weekly' => 15,
		'Monthly' => 30,
		'Quarterly' => 91,
		'Bi-Annually' => 183
	];
	$header = "";
	foreach ($lnks as $k=>$v) {
		if ($v != 7) { $header .= "&nbsp;&nbsp;|&nbsp;&nbsp;"; }
		if ($v == $duration) {
			$header .= "$k";
		} else {
			$header .= "<a href='". $thisScript. "?verb=summary&duration=$v'>$k</a>";
		}
	}

	$done = 0;
	$maxCycles = 6;
	$earlyIndex = $duration;
	$laterIndex = 0;
	$transactionGroups = [];
	while (!$done) {
		$maxCycles--;
		$laterDate = date('Y-m-d', strtotime("-". $laterIndex. " days"));
		$earlyDate = date('Y-m-d', strtotime("-". $earlyIndex. " days"));

		$transactionsGroup = $ledgerManager->retrieveGroupedSumsOfTransactionsAsObjects($earlyDate, $laterDate);
		$transactionGroups[] = $transactionsGroup;

		$laterIndex = $earlyIndex;
		$earlyIndex += $duration;
		if (empty($transactionsGroup) || !$maxCycles) {
			$done = 1;
		}
	}
	return [$header, $transactionGroups];
}
