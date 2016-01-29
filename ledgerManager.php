<?php

class LedgerManager {
	protected $pdo = null;
	protected $table = null;
	protected $overallBalance = null;

	/**
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
	 * for date range.
	 *
	 * @param bool $credit
	 * @param string $startDate
	 * @param string $endDate
	 * @return float
	 */
	protected function getTotalSumOfLikeTransactionsForWindow($credit, $startDate, $endDate)
	{
		$queryString = "
			select sum(amount) amount
			from {$this->table}
			where credit = :credit
		";
		if ($startDate) {
			$queryString .= " and time > :startDate";
		}
		if ($endDate) {
			$queryString .= " and time <= :endDate";
		}
		$query = $this->pdo->prepare($queryString);
		$query->bindParam(':credit', $credit);
		if ($startDate) {
			$query->bindParam(':startDate', $startDate);
		}
		if ($endDate) {
			$query->bindParam(':endDate', $endDate);
		}
		$query->execute();
		$row = $query->fetch(PDO::FETCH_ASSOC);
		return (float)$row['amount'];
	}

	/**
	 * @param string $startDate
	 * @param string $endDate
	 * @return float
	 */
	public function getCreditSumForWindow($startDate, $endDate)
	{
		return $this->getTotalSumOfLikeTransactionsForWindow(true, $startDate, $endDate);
	}

	/**
	 * @param string $startDate
	 * @param string $endDate
	 * @return float
	 */
	public function getDebitSumForWindow($startDate, $endDate)
	{
		return $this->getTotalSumOfLikeTransactionsForWindow(false, $startDate, $endDate);
	}

	/**
	 * This method returns a balance for a given date range.
	 * If no range is set, the current overall balance is calculated
	 * and set as the class property $overallBalance. This is the only
	 * place this value is set.
	 *
	 * @param string|null $startDate
	 * @param string|null $endDate
	 * @return void|float
	 */
	protected function calculateBalance($startDate = null, $endDate = null)
	{
		$totalCredits = $this->getCreditSumForWindow($startDate, $endDate);
		$totalDebits = $this->getDebitSumForWindow($startDate, $endDate);
		$balance = $totalCredits - $totalDebits;
		if (!$startDate && !$endDate) {
			$this->overallBalance = $balance;
		}
		return $balance;
	}

	/**
	 * This method returns a balance for a given date range, if set
	 * and appropriate the cached value is returned.
	 *
	 * @param string|null $startDate
	 * @param string|null $endDate
	 * @return float
	 */
	public function getBalance($startDate = null, $endDate = null)
	{
		if ($startDate || $endDate) {
			return $this->calculateBalance($startDate, $endDate);
		} elseif (!isset($this->overallBalance)) {
			return $this->calculateBalance();
		}
		return $this->overallBalance;
	}

	/**
	 * @return float
	 */
	public function getUnclearedAmount()
	{
		$query = $this->pdo->query("
			select sum(amount) amount
			from {$this->table}
			where credit = false and cleared = false
		");
		$row = $query->fetch(PDO::FETCH_ASSOC);
		return (float)$row['amount'];
	}

	/**
	 * @param string $cutOffDate
	 * @return mixed[]
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
		$query->execute([":cutOffDate" => $cutOffDate]);
		return $query->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * This method returns a list of grouped, summed transaction.  It's used
	 * to tally up common transaction types over a date range.  ie. How much
	 * money did I spend on beer this month?
	 *
	 * @param string $startDate
	 * @param string|null $endDate
	 * @return mixed[]
	 */
	public function retrieveGroupedSumsOfTransactions($startDate, $endDate = null)
	{
		$queryString = "
			select sum(amount) amount, description
			from {$this->table}
			where time > :startDate
		";
		if ($endDate) {
			$queryString .= " and time <= :endDate";
		}
		$queryString .= "
			and credit = 0
			group by description
			order by amount desc
		";
		$query = $this->pdo->prepare($queryString);
		$query->bindParam(':startDate', $startDate);
		if ($endDate) {
			$query->bindParam(':endDate', $endDate);
		}
		$query->execute();
		return $query->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * This window retrieves an array of transactions for a given date range.
	 * Note: The windowStartDate serves as a cutoff in terms of the returned date
	 * BUT will not affect the rolling balance which is calculated through the
	 * beginning of time.  ie. For a database containing transactions going back
	 * to 2014, a windowStartDate of 2015 would return 2015 through now(), but
	 * but the transactions in 2014 would setup the balance for 2015.
	 *
	 * @param string $windowStartDate
	 * @param string|null $endDate
	 * @param float|null $runningBalance
	 * @return mixed[]
	 */
	public function retrieveARangeOfTransactions($windowStartDate, $endDate = null)
	{
		$allTransactions = [];
		// Retrieve the balance of all transactions from epoch to $endDate
		$runningBalance = $this->getBalance(null, $endDate);

		$queryString = "
			select id, credit, description, amount, time, cleared
			from {$this->table}
			where time >= :windowStartDate
		";
		if ($endDate) {
			$queryString .= " and time <= :endDate";
		}
		$queryString .= " order by time desc";
		$query = $this->pdo->prepare($queryString);
		$query->bindParam(':windowStartDate', $windowStartDate);
		if ($endDate) {
			$query->bindParam(':endDate', $endDate);
		}
		$query->execute();
		$rows = $query->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rows as $row) {
			$row['balance'] = $runningBalance;
			$allTransactions[] = $row;
			// We're iterating through transactions backwards
			// in time, so getting the next value requires
			// ADDING debits and SUBTRACTING credits.
			if ($row['credit'] == 1) {
				$runningBalance -= $row['amount'];
			} else {
				$runningBalance += $row['amount'];
			}
		}
		return $allTransactions;
	}

	/**
	 * This calls retrieveARangeOfTransactions() and
	 * converts the associative array response to a standard class
	 *
	 * @param string $windowStartDate
	 * @param string|null $endDate
	 * @return stdClass[]
	 */
	public function retrieveARangeOfTransactionsAsObjects($windowStartDate, $endDate = null)
	{
		$transactionObjects = [];
		$transactions = $this->retrieveARangeOfTransactions($windowStartDate, $endDate);
		foreach ($transactions as $t) {
			$transaction = new stdClass();
			$transaction->id = $t['id'];
			$transaction->credit = $t['credit'];
			$transaction->description = $t['description'];
			$transaction->amount = $t['amount'];
			$transaction->time = $t['time'];
			$transaction->cleared = $t['cleared'];
			$transaction->balance = $t['balance'];
			$transaction->tshort = preg_replace('/\d{4}-(\d{2})-(\d{2}).*/', '$1/$2', $t['time']);
			$transactionObjects[] = $transaction;
		}
		return $transactionObjects;
	}

	/**
	 * This calls getAllUnclearedTransactionsOutsideCurrentWindow() and
	 * converts the associative array response to a standard class
	 *
	 * @param string $cutOffDate
	 * @return stdClass[]
	 */
	public function getAllUnclearedTransactionsOutsideCurrentWindowAsObjects($cutOffDate)
	{
		$transactionObjects = [];
		$transactions = $this->getAllUnclearedTransactionsOutsideCurrentWindow($cutOffDate);
		foreach ($transactions as $t) {
			$transaction = new stdClass();
			$transaction->id = $t['id'];
			$transaction->credit = $t['credit'];
			$transaction->description = $t['description'];
			$transaction->amount = $t['amount'];
			$transaction->time = $t['time'];
			$transaction->cleared = $t['cleared'];
			$transaction->tshort = preg_replace('/\d{4}-(\d{2})-(\d{2}).*/', '$1/$2', $t['time']);
			$transactionObjects[] = $transaction;
		}
		return $transactionObjects;
	}

	/**
	 * This calls retrieveGroupedSumsOfTransactions() and
	 * converts the associative array response to a standard class
	 *
	 * @param string $startDate
	 * @param string|null $endDate
	 * @return stdClass[]
	 */
	public function retrieveGroupedSumsOfTransactionsAsObjects($startDate, $endDate = null)
	{
		$transactionObjects = [];
		$transactions = $this->retrieveGroupedSumsOfTransactions($startDate, $endDate);
		if (empty($transactions)) {
			return [];
		}
		$debitSum = $this->getDebitSumForWindow($startDate, $endDate);
		$creditSum = $this->getCreditSumForWindow($startDate, $endDate);
		foreach ($transactions as $t) {
			$transaction = new stdClass();
			$transaction->amount = $t['amount'];
			$transaction->description = $t['description'];
			$transaction->percent = sprintf('%01.1f', 100*$t['amount']/$debitSum);
			$transactionObjects[] = $transaction;
		}
		$transactionGroup = new stdClass();
		$transactionGroup->startDate = $startDate;
		$transactionGroup->endDate = $endDate;
		$transactionGroup->debitSum = $debitSum;
		$transactionGroup->creditSum = $creditSum;
		$transactionGroup->transactions = $transactionObjects;
		return $transactionGroup;
	}

	/**
	 * This method assumes that the pay days we're looking for are
	 * the 15th and last day of the month.  No regard is given to
	 * weekends, which is on purpose for as-eve-as-possible
	 * distribution of allowance funds.
	 *
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
	 * @param string $description
	 * @param float $amount
	 * @param bool $credit
	 * @return bool
	 */
	public function validateCreate($description, $amount, $credit)
	{
		return $description && $amount && !is_null($credit);
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
		return $id && $description && $amount && !is_null($cleared);
	}

	/**
	 * @param int $id
	 * @return bool
	 */
	public function validateDelete($id)
	{
		return $id;
	}
}
