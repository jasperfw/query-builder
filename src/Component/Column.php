<?php

namespace JasperFW\QueryBuilder\Component;

use JasperFW\DataAccess\DAO;
use JasperFW\QueryBuilder\Query;

/**
 * Class Column
 *
 * Represents a single column in a database query.
 *
 * @package JasperFW\QueryBuilder\Component
 */
class Column
{
    /** @var string The proper name of the column */
    protected $name;
    /** @var string The nickname of the column */
    protected $alias;
    /** @var string The name of the associated parameter in the parameters array */
    protected $paramName;

    /**
     * Column constructor.
     *
     * @param string $name      The name of the column, typically preceeded with the table name
     * @param string $alias     The alias of the column
     * @param string $paramName The name of the parameter
     */
    public function __construct(string $name, string $alias, string $paramName)
    {
        $this->name = $name;
        $this->alias = $alias;
        $this->paramName = $paramName;
    }

    /**
     * Depending on the type of query, generate an appropriate snippet to retrieve or insert a value for this column
     *
     * @param int $queryType
     * @param DAO $dbc
     *
     * @return string The query snippet
     */
    public function generateSnippet(int $queryType, DAO $dbc): string
    {
        if ($queryType === Query::SELECT) {
            $alias = (!empty($this->alias) && $this->alias != $this->name) ? ' AS ' . $this->alias : '';
            return $dbc->escapeColName($this->name) . $alias;
        } elseif ($queryType === Query::INSERT || $queryType === Query::UPDATE) {
            return $dbc->escapeColName($this->name) . ' = ' . $dbc->makeParameterLabel($this->paramName);
        } else {
            return '';
        }
    }
}