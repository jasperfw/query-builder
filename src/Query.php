<?php /** @noinspection SqlDialectInspection */

namespace JasperFW\QueryBuilder;

use JasperFW\DataAccess\DAO;
use JasperFW\DataAccess\Dummy;
use JasperFW\DataAccess\Exception\DatabaseConnectionException;
use JasperFW\DataAccess\Exception\DatabaseQueryException;
use JasperFW\DataAccess\ResultSet\ResultSet;
use JasperFW\QueryBuilder\Component\Column;
use JasperFW\QueryBuilder\Component\Table;
use JetBrains\PhpStorm\Pure;
use RuntimeException;

/**
 * Class Query
 *
 * The Query class represents a database query. This can be a query string, the components to build a query, or a mix of
 * both.
 *
 * @package JasperFW\QueryBuilder
 */
class Query
{
    const SELECT = 1;
    const INSERT = 2;
    const UPDATE = 3;
    const DELETE = 4;

    /** @var int|null The type of query. This can be changed */
    protected ?int $queryType = null;
    /** @var string|null The template is the base query. This can be set manually, or by setting a query type. */
    protected ?string $template = null;
    /** @var array The parameters that will be used to fill in the query as needed. */
    protected array $parameters = [];
    /** @var Table[] The tables that will be queried. */
    protected array $tables = [];
    /** @var Column[] An array of columns that the query will be selecting inserting or updating */
    protected array $columns = [];
    /** @var array An array of individual where clauses */
    protected array $whereClauses = [];
    /** @var array An array of fields that will be sorted on */
    protected array $sortFields = [];
    /** @var int The number of records per page. Set to 0 if there will be no paging */
    protected int $pageSize = 0;
    /** @var int For paged result sets, this is the page of records to be retrived. */
    protected int $pageNumber = 0;
    /** @var DAO|Dummy The database connection */
    protected Dummy|DAO $dbc;
    /** @var ResultSet|null The statement object generated by the database connection for this query */
    protected ?ResultSet $statement;

    /**
     * Convenience function to allow creation and execution to be written as a single command.
     *
     * @param DAO|null $dbc
     *
     * @return Query The new query object
     * @throws DatabaseConnectionException
     */
    public static function build(?DAO $dbc = null): Query
    {
        return new Query($dbc);
    }

    /**
     * Checks if the query is built or null. If query is null creates a new query object
     *
     * @param Query|null $query The query object to check
     * @param DAO|null   $dbc   The database connection to which it belongs
     *
     * @return Query
     * @throws DatabaseConnectionException
     */
    public static function check(?Query &$query, ?DAO $dbc = null): Query
    {
        if (!$query instanceof Query) {
            $query = new Query();
        }
        if (null !== $dbc) {
            $query->setDBC($dbc);
        }
        return $query;
    }

    /**
     * Query constructor.
     *
     * @param DAO|null $dbc The database connection that will be used
     *
     * @throws DatabaseConnectionException
     */
    public function __construct(?DAO $dbc = null)
    {
        $this->dbc = $dbc ?? new Dummy();
    }

    /**
     * Set the database connection that this query will use. If the database connection does not change, this will not
     * override a stored statement. That way this query can be reused on a prepared statement even in setDBC is recalled
     * with the same value.
     *
     * @param DAO $dbc
     *
     * @return Query
     */
    public function setDBC(DAO $dbc): Query
    {
        if ($dbc !== $this->dbc) {
            $this->resetStatement();
            $this->dbc = $dbc;
        }

        return $this;
    }

    /**
     * This will be a select query
     *
     * @param string|null $template A replacement query template
     *
     * @return Query
     */
    public function select(?string $template = null): Query
    {
        if ($this->queryType !== self::SELECT) {
            $this->resetStatement();
            $this->queryType = self::SELECT;
        }
        return $this->template($template ?? 'SELECT {{columns}} FROM {{tables}} {{where}} {{sort}} {{pagination}}');
    }

    /**
     * This will be an insert query
     *
     * @param string|null $template A replacement query template
     *
     * @return Query
     */
    public function insert(?string $template = null): Query
    {
        if ($this->queryType !== self::INSERT) {
            $this->resetStatement();
            $this->queryType = self::INSERT;
        }
        return $this->template($template ?? 'INSERT {{columns}} INTO {{tables}}');
    }

    /**
     * This will be an update query
     *
     * @param string|null $template A replacement query template
     *
     * @return Query
     */
    public function update(?string $template = null): Query
    {
        if ($this->queryType !== self::UPDATE) {
            $this->resetStatement();
            $this->queryType = self::UPDATE;
        }
        return $this->template($template ?? 'UPDATE {{table}} SET {{columns}} {{where}}');
    }

    /**
     * This will be a delete query
     *
     * @param string|null $template A replacement query template
     *
     * @return Query
     */
    public function delete(?string $template = null): Query
    {
        if ($this->queryType !== self::DELETE) {
            $this->resetStatement();
            $this->queryType = self::DELETE;
        }
        return $this->template($template ?? 'DELETE FROM {{table}} {{where}}');
    }

    /**
     * Set a replacement template
     *
     * @param string $template The new template
     *
     * @return Query
     */
    public function template(string $template): Query
    {
        if ($template !== $this->template) {
            $this->resetStatement();
            $this->template = $template;
        }
        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Set the base table of the query
     *
     * @param string      $name  The name of the column, typically including database and schema if needed.
     * @param string|null $alias The nickname for the column as used in the rest of the query
     *
     * @return Query
     */
    public function table(string $name, ?string $alias = null): Query
    {
        $this->resetStatement();
        $this->tables['*'] = new Table($name, $alias, null, null);
        return $this;
    }

    /**
     * Create a basic join
     *
     * @param string      $name      The name of the column
     * @param string|null $alias     The alias of the column
     * @param string|null $condition The condition on which the columns are being joined
     *
     * @return Query
     */
    public function join(string $name, ?string $alias = null, ?string $condition = null): Query
    {
        $this->resetStatement();
        $key = $alias ?? $name;
        $this->tables[$key] = new Table($name, $alias, 'JOIN', $condition);
        return $this;
    }

    /**
     * Create a left join which will include everything in the previous table
     *
     * @param string      $name      The name of the column
     * @param string|null $alias     The alias of the column
     * @param string|null $condition The condition on which the columns are being joined
     *
     * @return Query
     */
    public function leftJoin(string $name, ?string $alias = null, ?string $condition = null): Query
    {
        $this->resetStatement();
        $key = $alias ?? $name;
        $this->tables[$key] = new Table($name, $alias, 'LEFT JOIN', $condition);
        return $this;
    }

    /**
     * Create a right join which will include everything in this table
     *
     * @param string      $name      The name of the column
     * @param string|null $alias     The alias of the column
     * @param string|null $condition The condition on which the columns are being joined
     *
     * @return Query
     */
    public function rightJoin(string $name, ?string $alias = null, ?string $condition = null): Query
    {
        $this->resetStatement();
        $key = $alias ?? $name;
        $this->tables[$key] = new Table($name, $alias, 'RIGHT JOIN', $condition);
        return $this;
    }

    /**
     * Create an inner join which will include that is in both tables
     *
     * @param string      $name      The name of the column
     * @param string|null $alias     The alias of the column
     * @param string|null $condition The condition on which the columns are being joined
     *
     * @return Query
     */
    public function innerJoin(string $name, ?string $alias = null, ?string $condition = null): Query
    {
        $this->resetStatement();
        $key = $alias ?? $name;
        $this->tables[$key] = new Table($name, $alias, 'INNER JOIN', $condition);
        return $this;
    }

    /**
     * Create an outer join wich will include everything in both tables
     *
     * @param string      $name      The name of the column
     * @param string|null $alias     The alias of the column
     * @param string|null $condition The condition on which the columns are being joined
     *
     * @return Query
     */
    public function outerJoin(string $name, ?string $alias = null, ?string $condition = null): Query
    {
        $this->resetStatement();
        $key = $alias ?? $name;
        $this->tables[$key] = new Table($name, $alias, 'OUTER JOIN', $condition);
        return $this;
    }

    /**
     * Generate the tables portion of the query
     *
     * @return string
     */
    #[Pure] public function generateTables(): string
    {
        $snippets = [];
        foreach ($this->tables as $table) {
            $snippet = $table->generateSnippet($this->queryType, $this->dbc);
            if (!empty($snippet)) {
                $snippets[] = $snippet;
            }
        }
        return implode(', ', $snippets);
    }

    /**
     * Create a column. Optionally specify a value if the query is an INSERT or UPDATE
     *
     * @param string      $name      The name of the column, should include the table name as well if multiple tables
     *                               are being used.
     * @param string|null $alias     The alias of the column
     * @param string|null $value     The value that will be set if this is an INSERT or UPDATE
     * @param string|null $paramName The name of the parameter. Generally, this is autogenerated and does not need to
     *                               be set.
     *
     * @return Query
     */
    public function column(string $name, ?string $alias = null, ?string $value = null, ?string $paramName = null): Query
    {
        $this->resetStatement();
        $key = $alias ?? $name;
        $paramName = $paramName ?? $key;
        $paramName = $this->sanitizeParameterName($paramName);
        $this->columns[$key] = new Column($name, $key, $paramName);
        $this->parameters[$paramName] = $value;
        return $this;
    }

    /**
     * Generate an appropriate list of columns based on the query type.
     *
     * @return string The SQL snippet of the column list
     */
    public function generateColumns(): string
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $snip = $column->generateSnippet($this->queryType, $this->dbc);
            if (!empty($snip)) {
                $columns[] = $snip;
            }
        }
        return implode(', ', $columns);
    }

    /**
     * Add a where clause. Pass in any parameters that are incorporated into the where cluase.
     *
     * @param string $clause The new where clause to add
     * @param array  $params Array of parameters and values included in the where clause
     *
     * @return Query
     */
    public function where(string $clause, array $params = []): Query
    {
        $this->resetStatement();
        $this->whereClauses[] = $clause;
        foreach ($params as $key => $value) {
            $this->parameters[$key] = $value;
        }
        return $this;
    }

    /**
     * Generate the where string based on the where clauses.
     *
     * @param string|null $prepend String such as WHERE or AND that should preceed the where string. If null, will add
     *                             "WHERE " before the clauses.
     *
     * @return string SQL snippet
     */
    public function generateWhere(?string $prepend = null): string
    {
        if (null === $prepend) {
            $prepend = 'WHERE';
        }
        return $this->dbc->generateWhere($this->whereClauses, $prepend);
    }

    /**
     * Add a parameter and set the value, or change the value of a parameter already set.
     *
     * @param string $name  The name of the parameter
     * @param mixed  $value The value of the parameter
     *
     * @return Query
     */
    public function parameter(string $name, mixed $value): Query
    {
        $name = $this->sanitizeParameterName($name);
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * Get the value assigned to the specified parameter. If the parameter doesn't exist, returns null.
     *
     * @param string $name The name of the parameter to retrieve
     *
     * @return string|null The value of the parameter or null if the parameter is not set.
     */
    public function getParameter(string $name): ?string
    {
        return $this->parameters[$this->sanitizeParameterName($name)] ?? null;
    }

    /**
     * Get an array of parameters
     *
     * @return array
     */
    #[Pure] public function getParameters(): array
    {
        $array = [];
        foreach ($this->parameters as $name => $parameter) {
            $array[$this->dbc->makeParameterLabel($name)] = $parameter;
        }
        return $array;
    }

    /**
     * Remove the specified parameter from the parameters array
     *
     * @param string $name
     *
     * @return Query
     */
    public function removeParameter(string $name): Query
    {
        $name = $this->sanitizeParameterName($name);
        if (isset($this->parameters[$name])) {
            unset ($this->parameters[$name]);
        }
        return $this;
    }

    public function pageSize(int $pageSize): Query
    {
        $this->resetStatement();
        $this->pageSize = $pageSize;
        return $this;
    }

    public function pageNumber(int $pageNumber): Query
    {
        $this->resetStatement();
        $this->pageNumber = $pageNumber;
        return $this;
    }

    /**
     * Uses the page size and page number to create a SQL snippet to limit the records returned to correspond to a
     * specific page. Only applicable to SELECT queries.
     *
     * @return string The SQL snippet
     */
    #[Pure] public function generatePagination(): string
    {
        return $this->dbc->generatePagination($this->pageSize, $this->pageNumber);
    }

    public function sortBy(string $field, string $direction = 'ASC'): Query
    {
        $this->sortFields[$field] = $direction;
        return $this;
    }

    /**
     * Generate an order by string ready for addition to a query
     *
     * @param string|null $prepend Value to prepend to the sort string.
     *
     * @return string
     */
    public function generateSort(?string $prepend = null): string
    {
        return $this->dbc->generateSort($this->sortFields, $prepend);
    }

    /**
     * Get ready to query the database by getting a statement object
     *
     * @return Query
     */
    public function prepare(): Query
    {
        if (null === $this->dbc) {
            throw new RuntimeException('No database connection set for query.');
        }
        $this->statement = $this->dbc->getStatement($this->generateQuery());
        return $this;
    }

    /**
     * Generate the query string to be passed to the database engine
     */
    public function generateQuery(): string
    {
        $query = $this->template;
        $query = str_replace('{{columns}}', $this->generateColumns(), $query);
        $query = str_replace('{{pagination}}', $this->generatePagination(), $query);
        $query = str_replace('{{tables}}', $this->generateTables(), $query);
        $matches = [];
        $i = preg_match('/{{where(\|([a-zA-Z .,\-]*))?}}/', $query, $matches);
        if ($i) {
            $prepend = $matches[2] ?? null;
            $query = str_replace($matches[0], $this->generateWhere($prepend), $query);
        }
        $i = preg_match('/{{sort(\|([a-zA-Z .,\-]*))?}}/', $query, $matches);
        if ($i) {
            $prepend = $matches[2] ?? null;
            $query = str_replace($matches[0], $this->generateSort($prepend), $query);
        }
        return $query;
    }

    /**
     * Execute the query with the set parameters
     *
     * @return ResultSet The result set object
     * @throws DatabaseQueryException
     */
    public function execute(): ResultSet
    {
        if (null === $this->statement) {
            $this->prepare();
        }
        return $this->statement->execute($this->getParameters());
    }

    /**
     * Strip any non alphanumeric characters from the passed parameter name
     *
     * @param string $parameterName The parameter name to sanitize
     *
     * @return string The sanitized name
     */
    protected function sanitizeParameterName(string $parameterName): string
    {
        return preg_replace('/[\W]/', '', $parameterName);
    }

    /**
     * Clears out the existing statement object. Intended to be called in any method that alters the query structure.
     */
    protected function resetStatement(): void
    {
        $this->statement = null;
    }
}
