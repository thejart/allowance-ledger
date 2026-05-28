<?php

use PHPUnit\Framework\TestCase;

class LedgerManagerTest extends TestCase
{
    private static PDO $pdo;
    private static string $table = 'test_ledger';
    private LedgerManager $manager;

    public static function setUpBeforeClass(): void
    {
        $env = explode("\n", file_get_contents(__DIR__ . '/../.env.test'));
        [$user, $password, $database, $table] = $env;
        self::$table = trim($table);

        $dsn = "mysql:host=127.0.0.1;port=3306;dbname=" . trim($database) . ";charset=utf8mb4";
        self::$pdo = new PDO($dsn, trim($user), trim($password), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        self::$pdo->exec("DROP TABLE IF EXISTS `" . self::$table . "`");
        self::$pdo->exec("
            CREATE TABLE `" . self::$table . "` (
                `id`          int(11)      NOT NULL AUTO_INCREMENT,
                `credit`      tinyint(1)   DEFAULT NULL,
                `description` varchar(32)  DEFAULT NULL,
                `amount`      double(8,2)  DEFAULT NULL,
                `time`        timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `cleared`     tinyint(1)   NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                UNIQUE KEY `time` (`time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // manager is instantiated fresh per test in setUp() to reset the balance cache
    }

    public static function tearDownAfterClass(): void
    {
        self::$pdo->exec("DROP TABLE IF EXISTS `" . self::$table . "`");
    }

    protected function setUp(): void
    {
        self::$pdo->exec("DELETE FROM `" . self::$table . "`");
        // Fresh instance per test so the $overallBalance cache doesn't bleed between tests
        $env = explode("\n", file_get_contents(__DIR__ . '/../.env.test'));
        [$user, $password, $database, $table] = $env;
        $this->manager = new LedgerManager(trim($user), trim($password), trim($database), self::$table, self::$pdo);
    }

    // --- helpers ---

    private function insert(string $description, float $amount, bool $credit, string $time, bool $cleared = false): int
    {
        $stmt = self::$pdo->prepare("
            INSERT INTO `" . self::$table . "` (description, amount, credit, time, cleared)
            VALUES (:description, :amount, :credit, :time, :cleared)
        ");
        $stmt->execute([
            ':description' => $description,
            ':amount'      => $amount,
            ':credit'      => (int)$credit,
            ':time'        => $time,
            ':cleared'     => (int)$cleared,
        ]);
        return (int)self::$pdo->lastInsertId();
    }

    private function rowCount(): int
    {
        return (int)self::$pdo->query("SELECT COUNT(*) FROM `" . self::$table . "`")->fetchColumn();
    }

    // --- CRUD ---

    public function testInsertTransaction(): void
    {
        $result = $this->manager->insertTransaction('coffee', 4.50, false);
        $this->assertTrue($result);
        $this->assertSame(1, $this->rowCount());
    }

    public function testInsertTransactionStripesCommaFromAmount(): void
    {
        $this->manager->insertTransaction('furniture', '1,200.00', false);
        $row = self::$pdo->query("SELECT amount FROM `" . self::$table . "`")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1200.0, (float)$row['amount']);
    }

    public function testUpdateTransaction(): void
    {
        $id = $this->insert('groceries', 50.00, false, '2025-01-01 10:00:00');
        $this->manager->updateTransaction($id, 'groceries updated', 55.00, 1);

        $row = self::$pdo->query("SELECT * FROM `" . self::$table . "` WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('groceries updated', $row['description']);
        $this->assertSame(55.0, (float)$row['amount']);
        $this->assertSame('1', (string)$row['cleared']);
    }

    public function testUpdateTransactionStripsCommaFromAmount(): void
    {
        $id = $this->insert('rent', 1000.00, false, '2025-01-02 10:00:00');
        $this->manager->updateTransaction($id, 'rent', '1,050.00', 0);

        $row = self::$pdo->query("SELECT amount FROM `" . self::$table . "` WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1050.0, (float)$row['amount']);
    }

    public function testDeleteTransaction(): void
    {
        $id = $this->insert('snacks', 3.00, false, '2025-01-03 10:00:00');
        $this->assertSame(1, $this->rowCount());

        $this->manager->deleteTransaction($id);
        $this->assertSame(0, $this->rowCount());
    }

    public function testRetrieveTransaction(): void
    {
        $id = $this->insert('salary', 500.00, true, '2025-01-04 10:00:00');
        $t = $this->manager->retrieveTransaction($id);

        $this->assertSame($id, $t->id);
        $this->assertSame('salary', $t->description);
        $this->assertSame('500.00', $t->amount);
        $this->assertSame('1', (string)$t->credit);
        $this->assertSame('500.00', $t->formattedAmount);
    }

    public function testRetrieveTransactionFormatsDebitWithParens(): void
    {
        $id = $this->insert('lunch', 12.50, false, '2025-01-05 10:00:00');
        $t = $this->manager->retrieveTransaction($id);
        $this->assertSame('(12.50)', $t->formattedAmount);
    }

    // --- balance ---

    public function testGetBalanceReturnsZeroWithNoTransactions(): void
    {
        $this->assertSame(0.0, $this->manager->getBalance());
    }

    public function testGetBalanceCreditsMinusDebits(): void
    {
        $this->insert('paycheck', 1000.00, true,  '2025-01-01 10:00:00');
        $this->insert('rent',     600.00,  false, '2025-01-02 10:00:00');
        $this->insert('food',     80.00,   false, '2025-01-03 10:00:00');

        $this->assertSame(320.0, $this->manager->getBalance());
    }

    public function testGetBalanceForDateRange(): void
    {
        $this->insert('paycheck', 1000.00, true,  '2025-01-01 10:00:00');
        $this->insert('rent',     600.00,  false, '2025-01-05 10:00:00');
        $this->insert('bonus',    200.00,  true,  '2025-02-01 10:00:00');

        $balance = $this->manager->getBalance('2025-01-01', '2025-01-31');
        $this->assertSame(400.0, $balance);
    }

    public function testGetBalanceCachesOverallValue(): void
    {
        $this->insert('paycheck', 500.00, true, '2025-01-01 10:00:00');
        $first  = $this->manager->getBalance();
        $second = $this->manager->getBalance();
        $this->assertSame($first, $second);
    }

    // --- uncleared ---

    public function testGetUnclearedAmountSumsUnclearedDebits(): void
    {
        $this->insert('pending bill', 75.00, false, '2025-01-01 10:00:00', false);
        $this->insert('cleared bill', 25.00, false, '2025-01-02 10:00:00', true);
        $this->insert('credit',      100.00, true,  '2025-01-03 10:00:00', false);

        $this->assertSame(75.0, $this->manager->getUnclearedAmount());
    }

    public function testGetUnclearedAmountReturnsZeroWhenNone(): void
    {
        $this->assertSame(0.0, $this->manager->getUnclearedAmount());
    }

    // --- range queries ---

    public function testRetrieveARangeOfTransactionsReturnsCorrectCount(): void
    {
        $this->insert('paycheck', 500.00, true,  '2025-01-01 10:00:00');
        $this->insert('rent',     400.00, false, '2025-01-10 10:00:00');
        $this->insert('coffee',     5.00, false, '2025-01-20 10:00:00');

        $results = $this->manager->retrieveARangeOfTransactions('2025-01-01');
        $this->assertCount(3, $results);
    }

    public function testRetrieveARangeOfTransactionsRunningBalance(): void
    {
        $this->insert('paycheck', 500.00, true,  '2025-01-01 10:00:00');
        $this->insert('rent',     200.00, false, '2025-01-02 10:00:00');

        // Results are newest-first; first item is most recent (rent), balance = 300
        $results = $this->manager->retrieveARangeOfTransactions('2025-01-01');
        $this->assertSame(300.0, $results[0]->balance);
        $this->assertSame(500.0, $results[1]->balance);
    }

    public function testRetrieveARangeOfTransactionsRespectsEndDate(): void
    {
        $this->insert('jan',  10.00, false, '2025-01-15 10:00:00');
        $this->insert('feb',  20.00, false, '2025-02-15 10:00:00');
        $this->insert('mar',  30.00, false, '2025-03-15 10:00:00');

        $results = $this->manager->retrieveARangeOfTransactions('2025-01-01', '2025-02-28');
        $this->assertCount(2, $results);
    }

    public function testGetAllUnclearedTransactionsBeforeCutoffDate(): void
    {
        $this->insert('old uncleared', 50.00, false, '2024-12-01 10:00:00', false);
        $this->insert('new uncleared', 30.00, false, '2025-06-01 10:00:00', false);
        $this->insert('old cleared',   20.00, false, '2024-11-01 10:00:00', true);

        $results = $this->manager->getAllUnclearedTransactionsBeforeCutoffDate('2025-01-01');
        $this->assertCount(1, $results);
        $this->assertSame('old uncleared', $results[0]->description);
    }

    // --- summary / grouping ---

    public function testRetrieveGroupedSumsOfTransactions(): void
    {
        $this->insert('coffee', 4.00, false, '2025-01-01 10:00:00');
        $this->insert('coffee', 5.00, false, '2025-01-02 10:00:00');
        $this->insert('lunch',  12.00, false, '2025-01-03 10:00:00');
        $this->insert('credit', 100.00, true, '2025-01-04 10:00:00');

        $rows = $this->manager->retrieveGroupedSumsOfTransactions('2024-12-31');
        $this->assertCount(2, $rows);

        $byDesc = array_column($rows, null, 'description');
        $this->assertSame('9.00', number_format($byDesc['coffee']['amount'], 2));
        $this->assertSame('12.00', number_format($byDesc['lunch']['amount'], 2));
    }

    public function testRetrieveChunksOfGroupedTransactionsPageSize(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $date = sprintf('2024-%02d-01 10:00:00', $i);
            $this->insert("expense $i", 10.00, false, $date);
        }

        $groups = $this->manager->retrieveChunksOfGroupedTransactions(30);
        $this->assertLessThanOrEqual(LedgerManager::SUMMARY_PAGE_SIZE, count($groups));
    }

    public function testRetrieveChunksOfGroupedTransactionsWithOffset(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $date = date('Y-m-d H:i:s', strtotime("-$i months"));
            $this->insert("expense $i", 10.00, false, $date);
        }

        $firstPage  = $this->manager->retrieveChunksOfGroupedTransactions(30, LedgerManager::SUMMARY_PAGE_SIZE, 0);
        $secondPage = $this->manager->retrieveChunksOfGroupedTransactions(30, LedgerManager::SUMMARY_PAGE_SIZE, 30 * LedgerManager::SUMMARY_PAGE_SIZE);

        $firstDates  = array_column($firstPage, 'startDate');
        $secondDates = array_column($secondPage, 'startDate');
        $this->assertEmpty(array_intersect($firstDates, $secondDates));
    }

    // --- numberOfDaysLeftInPayPeriod ---

    public function testNumberOfDaysLeftInPayPeriodReturnsPositiveInt(): void
    {
        $days = $this->manager->numberOfDaysLeftInPayPeriod();
        $this->assertIsInt($days);
        $this->assertGreaterThanOrEqual(1, $days);
        $this->assertLessThanOrEqual(31, $days);
    }

    // --- validation ---

    public function testValidateCreateRequiresAllFields(): void
    {
        $this->assertTrue($this->manager->validateCreate('coffee', 4.50, 0));
        $this->assertFalse($this->manager->validateCreate('', 4.50, 0));
        $this->assertFalse($this->manager->validateCreate('coffee', 0, 0));
        $this->assertFalse($this->manager->validateCreate('coffee', 4.50, null));
    }

    public function testValidateUpdateRequiresAllFields(): void
    {
        $this->assertTrue($this->manager->validateUpdate(1, 'coffee', 4.50, 0));
        $this->assertFalse($this->manager->validateUpdate(0, 'coffee', 4.50, 0));
        $this->assertFalse($this->manager->validateUpdate(1, '', 4.50, 0));
        $this->assertFalse($this->manager->validateUpdate(1, 'coffee', 0, 0));
        $this->assertFalse($this->manager->validateUpdate(1, 'coffee', 4.50, null));
    }

    public function testValidateDeleteRequiresInteger(): void
    {
        $this->assertTrue($this->manager->validateDelete(1));
        $this->assertTrue($this->manager->validateDelete(0));
        $this->assertFalse($this->manager->validateDelete('1'));
        $this->assertFalse($this->manager->validateDelete(null));
    }
}
