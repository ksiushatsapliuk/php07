<?php
// Рахуємо виконані SQL-запити, а не створення підготовлених виразів.
class MeasuredPDO extends PDO
{
    public int $queryCount = 0;

    public function __construct(string $dsn, string $user, string $password, array $options)
    {
        parent::__construct($dsn, $user, $password, $options);

        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [MeasuredStatement::class, [$this]]);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $this->queryCount++;

        if ($fetchMode === null) {
            return parent::query($query);
        }

        return parent::query($query, $fetchMode, ...$args);
    }

}

class MeasuredStatement extends PDOStatement
{
    protected function __construct(private MeasuredPDO $connection)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->connection->queryCount++;

        return parent::execute($params);
    }
}
