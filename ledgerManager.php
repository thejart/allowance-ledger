<?php

class LedgerManager {
	protected $pdo = null;
	protected $table = null;
	protected $currentBalance = null;

	public function __construct($username, $password, $database, $table = 'ledger')
	{
		$this->pdo = new PDO("mysql:host=localhost;dbname=". $database, $username, $password);
		$this->table = $table;
	}

	public function updateTransaction($transactionId, $description, $amount, $cleared)
	{
		$query = $this->pdo->prepare("
			update {$this->table}
			set description = :description,
			set amount = :amount,
			set cleared = :cleared
			where id = :transactionId
		");
		return $query->execute([
			':description' => $description,
			':amount' => $amount,
			':cleared' => $cleared,
			':transactionId' => $transactionId
		]);
	}

	public function insertTransaction($description, $amount, $credit)
	{
		$query = $this->pdo->prepare("
			insert into {$this->table}
			(description, amount, credit)
			values (:description, :amount, :credit)
		");
		return $query->execute([
			':description' => $description,
			':amount' => $amount,
			':credit' => $credit,
		]);
	}

	public function deleteTransaction($transactionId)
	{
		$query = $this->pdo->prepare("
			delete from {$this->table}
			where id = :transactionId
		");
		return $query->execute([':transactionId' => $transactionId]);
	}

	/**
	 * This method returns the sum of all credit or debit transactions
	 *
	 * @param bool $credit
	 * @return float
	 */
	protected function getTotalSumCreditTransactions($credit = true)
	{
		// TODO: add support for a time range which can be used on the summary page
		$query = $this->pdo->prepare("
			select sum(amount) amount
			from {$this->table}
			where credit = :credit
		");
		$query->execute([':credit' => $credit]);
		$row = $query->fetch(PDO::FETCH_ASSOC);
		return (float)$row['amount'];
	}

	/**
	 * This method returns an array of the current balance, debit, and credit values
	 *
	 * @return float[]
	 */
	protected function calculateBalance()
	{
		$totalCredits = $this->getTotalSumCreditTransactions(true);
		$totalDebits = $this->getTotalSumCreditTransactions(false);
		$this->currentBalance = $totalCredits - $totalDebits;
	}

	public function getCurrentBalance()
	{
		if (!isset($this->currentBalance)) {
			$this->calculateBalance();
		}
		return $this->currentBalance;
	}

	public function getUnclearedAmount()
	{
		$query = $this->pdo->prepare("
			select sum(amount) amount
			from {$this->table}
			where credit = false and cleared = false
		");
		$row = $query->fetch(PDO::FETCH_ASSOC);
		return (float)$row['amount'];
	}

	protected function getNextPayday()
	{
		// TODO: 
		// if today <= 15 the return 15th of month
		// else select last_day(now())
	}

	public function numberOfDaysLeftInPayPeriod()
	{
		// use the results of $this->getNextPayday() to figure this out
	}

	public function retrieveARangeOfTransactions($startDate, $endDate = 'now()', $runningBalance = null)
	{
		// TODO: finalize support for last two parmas.  this will be handy when requesting
		// more transactions from the past
		$allTransactions = [];
		if (!$runningBalance) {
			$runningBalance = $this->getCurrentBalance();
		}

		$query = $this->pdo->prepare("
			select id, credit, description, amount, time, cleared
			from {$this->table}
			where time >= :startDate
			and time <= :endDate
			order by time desc
		");
		$query->execute([
			":startDate" => $startDate,
			":endDate" => $endDate
		]);

		$rows = $query->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$row['balance'] = $runningBalance;
			$allTransactions[] = $row;
			// Realize that we're calculating balances backwards in time
			if ($row['credit'] == 1) {
				$runningBalance -= $row['amount'];
			} else {
				$runningBalance += $row['amount'];
			}
		}
		return $allTransactions;
	}

	public function getAllUnclearedTransactionsOutsideCurrentWindow($startDate)
	{
		// select id,credit,description,amount,time,cleared from ledger
		// where time<='$dateInThePast' and cleared=0 and credit=0 order by time desc"

	}
}

class LedgerSummaryManager
{
	/**
	 *
	 * This will be a set of methods for grouping up sums over durations
	 * ...and may end up being in a separate file
	 * ...then again, it may just be more methods in the LedgerManager class
	 *
	 */

	public function retrieveARangeOfGroupedTransactions($startDate, $endDate = 'now')
	{
	}

	/**
	 * Per group block we need start and end dates, and total credit and debit amounts.
	 * Per group row we need total amount and percentage of total debited
	 *
	 */
}
