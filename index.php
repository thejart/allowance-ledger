<?php
$startTime = microtime(true);
require 'ledgerManager.php';
// Credit to https://github.com/adamtomecek/Template
require 'templateManager/Template.php';

$pathArray = pathinfo(__FILE__);
$path = preg_replace('/\/var\/www\//', '', $pathArray['dirname']). "/";
$thisScript = $pathArray['basename'];
$host = $_SERVER['HTTP_HOST'];
list($user, $password, $database, $table) = setupEnvironment();
$ledgerManager = new LedgerManager($user, $password, $database, $table);

$verb = getRequestParam('verb', 'transactions');
$id = (int)getRequestParam('id');
$description = getRequestParam('description');
$amount = getRequestParam('amount');
$credit = getRequestParam('credit');
$cleared = getRequestParam('cleared', 0);
$duration = getRequestParam('duration', 30);
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
	elseif ($verb == 'transactions') {
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->totalBalance = $ledgerManager->getBalance();
		$template->daysLeft = $ledgerManager->numberOfDaysLeftInPayPeriod();
		$template->unclearedAmount = sprintf('%01.2f', $ledgerManager->getUnclearedAmount());
		$template->transactions = $ledgerManager->retrieveARangeOfTransactions($windowStartDate);
		$template->unclearedTransactions = $ledgerManager->getAllUnclearedTransactionsBeforeCutoffDate($windowStartDate);
		$template->budgetLiClass = 'class="active"';
		$template->summaryLiClass = '';
		$template->nextWindowEnd = $windowStartDate;
		$template->setFile('templates/bs-table-transactions.phtml')
			->setLayout('templates/@bs-layout.phtml')
			->render();
	}
	elseif ($verb == 'moreTransactions') {
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
		$template->setFile('templates/more-transactions.phtml')
			->setLayout('templates/@null-layout.phtml')
			->render();
	}
	elseif ($verb == 'summary') {
		list($durationDescriptions, $transactionGroups) = setupSummary($duration, $ledgerManager);
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->totalBalance = $ledgerManager->getBalance();
		$template->daysLeft = $ledgerManager->numberOfDaysLeftInPayPeriod();
		$template->duration = $duration;
		$template->durationDescriptions = $durationDescriptions;
		$template->transactionGroups = $transactionGroups;
		$template->budgetLiClass = '';
		$template->summaryLiClass = 'class="active"';
		$template->setFile('templates/bs-table-summary.phtml')
			->setLayout('templates/@bs-layout.phtml')
			->render();
	}
	elseif ($verb == 'updateModal') {
		$transaction = $ledgerManager->retrieveTransaction($id);
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->id = $transaction->id;
		$template->description = $transaction->description;
		$template->amount = $transaction->amount;
		$template->cleared = $transaction->cleared;
		$template->time = date('M j, Y g:i A', strtotime($transaction->time));
		$template->setFile('templates/update-modal.phtml')
			->setLayout('templates/@null-layout.phtml')
			->render();
	}
	elseif ($verb == 'deleteModal') {
		$transaction = $ledgerManager->retrieveTransaction($id);
		$template = new Template();
		$template->thisScript = $thisScript;
		$template->id = $transaction->id;
		$template->description = $transaction->description;
		$template->amount = $transaction->amount;
		$template->time = date('M j, Y g:i A', strtotime($transaction->time));
		$template->setFile('templates/delete-modal.phtml')
			->setLayout('templates/@null-layout.phtml')
			->render();
	}
	elseif ($verb == 'calculator') {
        $checkBalance = (float)getRequestParam('checkBalance', 0);
        $savePlusSurplus = (float)getRequestParam('saveSurplus', 0);
        $outstandingBills = (float)getRequestParam('outstandingBills', 0);
        $outstandingOther = (float)getRequestParam('outstandingOther', 0);

        $ledgerBalance = $ledgerManager->getBalance();
        $unclearedAmount = $ledgerManager->getUnclearedAmount();
        $daysLeft = $ledgerManager->numberOfDaysLeftInPayPeriod();

	// (checking account balance) - (all outstanding expenses and surplus tallied in budget doc) - (ledgerBalance excluding uncleared items)
        $discrepancy = $checkBalance - ($savePlusSurplus + $outstandingBills + $outstandingOther) - ($ledgerBalance + $unclearedAmount);
        echo "<h1><p class='text-center bg-info'>". sprintf('%01.2f', $discrepancy). "</p></h1>";
    }
	else {
		echo '(nope)';
	}
}
$logString = "[jart] verb: ". $verb. " duration: ". $duration. " in secs: ". (microtime(true)-$startTime);
error_log($logString);

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
function setupSummary($duration, $ledgerManager) {
	$durationDescriptions = [
		'Weekly' => 7,
		'Bi-Weekly' => 15,
		'Monthly' => 30,
		'Quarterly' => 91,
		'Bi-Annually' => 183
	];
	$transactionGroups = $ledgerManager->retrieveChunksOfGroupedTransactions($duration);
	return [$durationDescriptions, $transactionGroups];
}
