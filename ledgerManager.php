<?php

class LedgerManager {
	protected $table = "ledger";

	public function updateTransaction($transactionId)
	{
		// update $this->table set description = $desc, $amount = $amount, cleared = $cleared
		// where id = $transactionId

		// there can be a separate clearTransaction() that calls this, if necessary
	}

	public function insertTransaction()
	{
		// insert into $this->table (description, amount, credit) values
		// ($desc, $amount, $credit)
	}

	public function deleteTransaction($transactionId)
	{
		// delete from $this->table where id = $transactionId
	}

	/**
	 * This method returns the sum of all credit or debit transactions
	 *
	 * @param bool $credit
	 * @return int
	 */
	protected function getTotalSumCreditTransactions($credit = true)
	{
		// select count(amount) from $this->table where credit = $credit
	}

	/**
	 * This method returns an array of the current balance, debit, and credit values
	 *
	 * @return int[]
	 */
	public function getBalance()
	{
		$totalCredits = $this->getTotalSumCreditTransactions(true);
		$totalDebits = $this->getTotalSumCreditTransactions(false);
		return [ $totalCredits-$totalDebits, $totalDebits, $totalCredits ];
	}

	public function getUnclearedAmount()
	{
		// select sum(amount) from $this->table where credit = false and cleared = false
	}

	protected function getNextPayday()
	{
		// TODO: figure out the best way to do this.  right now i'm parsing the output of `cal`
		// ...so stop doing that
	}

	public function numberOfDaysLeftInPayPeriod()
	{
		// use the results of $this->getNextPayday() to figure this out
	}

	public function retrieveARangeOfTransactions($startDate, $endDate = 'now')
	{
		/**
		 *
		 * runningBalance can be calculated on the fly, per row (runningBalance-previousAmount)
		 *
		 * return = [
		 *	[ description, amount, runningBalance ]
		 * ]
		 *
		 */
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
