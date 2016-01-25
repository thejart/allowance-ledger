<?php

class LedgerManager {
	protected $pdo = null;
	protected $table = null;
	protected $currentBalance = null;

	/**
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $databae
	 * @param string|null $table
	 */
	public function __construct($username, $password, $database, $table = 'ledger')
	{
		$this->pdo = new PDO("mysql:host=localhost;dbname=". $database, $username, $password);
		$this->table = $table;
	}

	/**
	 * @param int $transactionId
	 * @param string $description
	 * @param float $amount
	 * @param bool $cleared
	 * @return bool
	 */
	public function updateTransaction($transactionId, $description, $amount, $cleared)
	{
		$query = $this->pdo->prepare("
			update {$this->table}
			set description = :description,
			amount = :amount,
			cleared = :cleared
			where id = :transactionId
		");
		return $query->execute([
			':description' => $description,
			':amount' => $amount,
			':cleared' => $cleared,
			':transactionId' => $transactionId
		]);
	}

	/**
	 * @param string $description
	 * @param float $amount
	 * @param bool $cleared
	 * @return bool
	 */
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

	/**
	 * @param int $transactionId
	 * @return bool
	 */
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

	/**
	 * @return float
	 */
	public function getCurrentBalance()
	{
		if (!isset($this->currentBalance)) {
			$this->calculateBalance();
		}
		return $this->currentBalance;
	}

	/**
	 * @return float
	 */
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

	/**
	 * @return int
	 */
	public function numberOfDaysLeftInPayPeriod()
	{
		$todaysDate = (int)date('d');
		if ($todaysDate <= 15) {
			return 15 - $todaysDate + 1;
		} else {
			$query = $this->pdo->query("
				select (datediff(last_day(now()), now()) + 1) as daysLeft
			");
			$row = $query->fetch(PDO::FETCH_ASSOC);
			return (int)$row['daysLeft'];
		}
	}

	/**
	 * @param string $startDate
	 * @param string|null $endDate
	 * @param float|null $runningBalance
	 * @return mixed[]
	 */
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

	/**
	 * @param string $cutOffDate
	 */
	public function getAllUnclearedTransactionsOutsideCurrentWindow($cutOffDate)
	{
		$query = $this->pdo->prepare("
			select id, credit, description, amount, time, cleared
			from {$this->table}
			where time <= :cutOffDate
			and cleared=0
			and credit=0
			order by time desc
		");
		return $query->execute([":cutOffDate" => $cutOffDate]);
	}

	/**
	 * @param string $description
	 * @param float $amount
	 * @param bool $credit
	 * @return bool
	 */
	public function validateCreate($description, $amount, $credit)
	{
		return true;
	}

	/**
	 * @param int $id
	 * @param string $description
	 * @param float $amount
	 * @param bool $credit
	 * @return bool
	 */
	public function validateUpdate($id, $description, $amount, $cleared)
	{
		return true;
	}

	/**
	 * @param int $id
	 * @return bool
	 */
	public function validateDelete($id)
	{
		return true;
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
