# Jasper FW - Query Builder

A library for building SQL database queries. Unlike many other query builders that require the entire query be built in
the system, this library follows a hybrid approach, allowing the developer to create a complex query and pass it in as
a template, replacing certain tokens with autogenerated SQL snippets.

## Features

- Generates SQL queries from scratch
- Modifies passed SQL queries with generated SQL snippets

# Instructions

## Installation
Install using composer `composer require "jasperfw/query-builder"`

## Basic Usage
### Create a basic select
```php
$query = Query::build($dbc) // Pass in the database connection
    ->setDBC($dbc) // Alteratively, pass the database connection later
    ->template($hardcodedQuery) // Pass in a basic SQL query that will be modified
    ->select() // The query type, select, insert, update or delete
    ->table('schema.tblA', 'tblA') // The base table
    ->join('schema.tblB', 'b') // A simple join on a table with an alias
    ->leftJoin('schema.tblC', 'c', 'tblA.index = c.index') // More complex join with condition
    ->rightJoin('schema.tbld', 'd', 'tblA.index = d.index')
    ->innerJoin('schema.tblE', 'e', 'tblA.index = e.index')
    ->outerJoin('schema.tblF', 'f', 'tblA.index = f.index')
    ->column('table.colA', 'colA', 'bob', 'param') // A column, along with a parameter
    ->column('table.colB', 'colB', 'steve') // A column that will use a default parameter name
    ->column('table.colC', null, 'dave')
    ->where('colA = :a', ['a' => 'b']) // Where condition, with parameters
    ->sortBy('colA', 'ASC') // Sort, can be called multiple times
    ->pageNumber(2) // The page, can be ommitted if doing a limit query
    ->pageSize(50); // Number of records per page
```

The produces the following query (with newlines added for readablity):
```sql
SELECT [table.colA] AS colA, [table.colB] AS colB, [table.colC] AS table.colC 
FROM schema.tblA tblA,
    schema.tblB b, 
    schema.tblC c ON tblA.index = c.index, 
    schema.tbld d ON tblA.index = d.index, 
    schema.tblE e ON tblA.index = e.index, 
    schema.tblF f ON tblA.index = f.index 
WHERE colA = :a
ORDER BY colA ASC
LIMIT 50,50
```

### Use the Query object as a prepared statement
Query objects can be reused for efficiency.
```php
$query->prepare(); // Generates a statement to be used
$query->parameter('a', 'newValue');
$result = $query->execute(); // Execute the query, returns a JasperFW/DataAccess/ResultSet::ResultSet object
```
Note that if the query structure is changed after the query is executed, the ResultSet object returned by `execute` will
be invalidated. These changes are any changes to columns, tables, query type, pagination. Changes to the parameters will
not cause this. Typically, changing the table structure after executing is not recommended, and instead a new Query
should be created or the existing Query object should be cloned.

### Create a custom template
A base query can be passed in as a string using the `template()` function. The system will simply replace certain tokens
with generated SQL snippets. The query type setting function (`select()`, `insert()`, etc) must still be called so that
the Query object will generate the appropriate snippets. Note that the template can be passed to the query type function
as an argument, for simplicity.

The following tokens in a query will be replaced:

- {{columns}} will be replaced with a list of columns
- {{pagination}} will be replaced with either a limit or limit and offset
- {{tables}} will be replaced with the list of tables and joins
- {{where}} will be replaced with the where clause(s). By default, these where clauses are preceeded with "WHERE ". This
can be overridden by adding a | and a replacement keyword. eg `{{where|AND}}`. Spaces will be automatically placed.
- {{sort}} will be replaced with the sort clause(s). By default the sorts will be preceeded with "ORDER BY " but if the
`{{sort}}` is following existing sorts in the template, this can be overridden by adding a | and a new keywork or a
comma, eg `{{sort|,}}`

Note that pagination, sort and where clauses will only be added if the query builder has been passed additonal clauses.
This allows a good deal of flexibility for modifying queries on the fly.