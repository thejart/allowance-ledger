<?php
$startTime = microtime(true);
require 'ledgerManager.php';
require 'verb.php';
// Credit to https://github.com/adamtomecek/Template
require 'templateManager/Template.php';

$pathArray = pathinfo(__FILE__);
// $path should be used to point to the root web directory for this app, if it's not /
$path = "allowance-ledger/";
$thisScript = $pathArray['basename'];
$host = $_SERVER['HTTP_HOST'];
list($user, $password, $database, $table) = setupEnvironment();
$ledgerManager = new LedgerManager($user, $password, $database, $table);

$verb = getRequestParam('verb', Verb::TRANSACTIONS);
$id = (int)getRequestParam('id');
$description = getRequestParam('description');
$amount = getRequestParam('amount');
$credit = getRequestParam('credit');
$cleared = getRequestParam('cleared', 0);
$duration = getRequestParam('duration', 30);
$windowStartDate = date('Y-m-d', strtotime("-". $duration. " days"));

if (isset($verb)) {
	if ($verb == Verb::UPDATE &&
			$ledgerManager->validateUpdate($id, $description, $amount, $cleared)) {
		$ledgerManager->updateTransaction($id, $description, $amount, $cleared);
		error_log('updated transaction: '. $id);
		redirectTo("{$host}/{$path}{$thisScript}");
	}
	elseif ($verb == Verb::CREATE && $ledgerManager->validateCreate($description, $amount, $credit)) {
		$ledgerManager->insertTransaction($description, $amount, $credit);
		error_log('inserted transaction');
		redirectTo("{$host}/{$path}{$thisScript}");
	}
	elseif ($verb == Verb::DELETE && $ledgerManager->validateDelete($id)) {
		$ledgerManager->deleteTransaction($id);
		error_log('deleted transaction: '. $id);
		redirectTo("{$host}/{$path}{$thisScript}");
	}
	elseif ($verb == Verb::TRANSACTIONS) {
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->totalBalance = $ledgerManager->getBalance();
		$template->daysLeft = $ledgerManager->numberOfDaysLeftInPayPeriod();
		$template->unclearedAmount = number_format($ledgerManager->getUnclearedAmount(), 2);
		$template->transactions = $ledgerManager->retrieveARangeOfTransactions($windowStartDate);
		$template->unclearedTransactions = $ledgerManager->getAllUnclearedTransactionsBeforeCutoffDate($windowStartDate);
		$template->budgetLiClass = 'active';
		$template->summaryLiClass = '';
		$template->nextWindowEnd = $windowStartDate;
		$template->verbMoreTransactions = Verb::MORE_TRANSACTIONS;
		$template->verbCreate = Verb::CREATE;
		$template->setFile('templates/bs-table-transactions.phtml')
			->setLayout('templates/@bs-layout.phtml')
			->render();
	}
	elseif ($verb == Verb::MORE_TRANSACTIONS) {
		$windowEndDate = date('Y-m-d', strtotime(getRequestParam('windowEndDate')));
		$unixEndDate = strtotime($windowEndDate);
		$windowStartDate = date('Y-m-d', strtotime("-". $duration. " days", $unixEndDate));
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->windowEndDate = $windowEndDate;
		$template->windowStartDate = $windowStartDate;
		$template->transactions = $ledgerManager->retrieveARangeOfTransactions($windowStartDate, $windowEndDate);
		$template->unclearedTransactions = $ledgerManager->getAllUnclearedTransactionsBeforeCutoffDate($windowStartDate);
		$template->nextWindowEnd = $windowStartDate;
		$template->verbMoreTransactions = Verb::MORE_TRANSACTIONS;
		$template->setFile('templates/more-transactions.phtml')
			->setLayout('templates/@null-layout.phtml')
			->render();
	}
	elseif ($verb == Verb::SUMMARY) {
		list($durationDescriptions, $transactionGroups) = setupSummary($duration, $ledgerManager);
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->totalBalance = $ledgerManager->getBalance();
		$template->daysLeft = $ledgerManager->numberOfDaysLeftInPayPeriod();
		$template->duration = $duration;
		$template->durationDescriptions = $durationDescriptions;
		$template->transactionGroups = $transactionGroups;
		$template->nextOffsetDays = $duration * LedgerManager::SUMMARY_PAGE_SIZE;
		$template->budgetLiClass = '';
		$template->summaryLiClass = 'active';
		$template->verbMoreSummary = Verb::MORE_SUMMARY;
		$template->verbCreate = Verb::CREATE;
		$template->setFile('templates/bs-table-summary.phtml')
			->setLayout('templates/@bs-layout.phtml')
			->render();
	}
	elseif ($verb == Verb::MORE_SUMMARY) {
		$offsetDays = (int)getRequestParam('offsetDays', 0);
		$transactionGroups = $ledgerManager->retrieveChunksOfGroupedTransactions($duration, LedgerManager::SUMMARY_PAGE_SIZE, $offsetDays);
		$hasMoreData = (bool)array_filter($transactionGroups, function($g) {
			return !empty($g->transactions);
		});
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->duration = $duration;
		$template->transactionGroups = $transactionGroups;
		$template->hasMoreData = $hasMoreData;
		$template->nextOffsetDays = $offsetDays + $duration * LedgerManager::SUMMARY_PAGE_SIZE;
		$template->verbMoreSummary = Verb::MORE_SUMMARY;
		$template->setFile('templates/more-summary.phtml')
			->setLayout('templates/@null-layout.phtml')
			->render();
	}
	elseif ($verb == Verb::UPDATE_MODAL) {
		$transaction = $ledgerManager->retrieveTransaction($id);
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->id = $transaction->id;
		$template->description = $transaction->description;
		$template->amount = $transaction->amount;
		$template->cleared = $transaction->cleared;
		$template->time = date('M j, Y g:i A', strtotime($transaction->time));
		$template->verbUpdate = Verb::UPDATE;
		$template->verbDelete = Verb::DELETE;
		$template->setFile('templates/update-modal.phtml')
			->setLayout('templates/@null-layout.phtml')
			->render();
	}
	else {
		echo '(nope)';
	}
}
$logString = "[jart] verb: ". $verb. " duration: ". $duration. " in secs: ". (microtime(true)-$startTime);
error_log($logString);

function setupEnvironment() {
	try {
		$environmentFile = file_get_contents('.env');
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
function setupSummary($duration, $ledgerManager, $offsetDays = 0) {
	$durationDescriptions = [
		'Weekly' => 7,
		'Bi-Weekly' => 15,
		'Monthly' => 30,
		'Quarterly' => 91,
		'Bi-Annually' => 183
	];
	$transactionGroups = $ledgerManager->retrieveChunksOfGroupedTransactions($duration, LedgerManager::SUMMARY_PAGE_SIZE, $offsetDays);
	return [$durationDescriptions, $transactionGroups];
}
