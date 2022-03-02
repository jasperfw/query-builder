<?php

namespace JasperFW\QueryBuilder\Component;

use JasperFW\DataAccess\DAO;
use JasperFW\QueryBuilder\Query;
use JetBrains\PhpStorm\Pure;

/**
 * Class Table
 *
 * Represents a single table in a database query. Also includes information about how the table is joined.
 *
 * @package JasperFW\QueryBuilder\Component
 */
class Table
{
    protected string $table;
    protected ?string $alias;
    protected ?string $join;
    protected ?string $conditions;

    /**
     * Column constructor.
     *
     * @param string      $column     The column name
     * @param string|null $alias      The alias for the column used in the query
     * @param string|null $join       The type of join
     * @param string|null $conditions The matching conditions
     */
    public function __construct(string $column, ?string $alias, ?string $join, ?string $conditions)
    {
        $this->table = $column;
        $this->alias = $alias;
        $this->join = $join;
        $this->conditions = $conditions;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * @return string|null
     */
    public function getJoin(): ?string
    {
        return $this->join;
    }

    /**
     * @return string|null
     */
    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    /**
     * Get the SQL snippet for this column
     *
     * @param int $queryType          The type of query, SELECT, INSERT etc
     * @param DAO $databaseConnection The database connection that the snippet should be made for
     *
     * @return string The snippet for this column
     */
    #[Pure] public function generateSnippet(int $queryType, DAO $databaseConnection): string
    {
        switch ($queryType) {
            case Query::SELECT:
                return $this->getSelectSnippet();
            case Query::INSERT:
            case QUERY::UPDATE:
                return $this->getInsertUpdateSnippet();
            default: // Mostly, delete, which doesn't take a column conditions
                return '';
        }
    }

    /**
     * Get the SQL snippet for this column if this is a select query
     *
     * @return string The snippet
     */
    protected function getSelectSnippet(): string
    {
        $snippet = $this->table;
        $snippet .= ($this->alias !== null) ? ' ' . $this->alias : '';
        $snippet .= ($this->conditions !== null) ? ' ON ' . $this->conditions : '';
        return $snippet;
    }

    /**
     * Get the SQL snippet for this column if this is an insert or update query
     *
     * @return string The snippet for this column
     */
    protected function getInsertUpdateSnippet(): string
    {
        if ($this->join === null) {
            return $this->table;
        }
        return '';
    }
}
