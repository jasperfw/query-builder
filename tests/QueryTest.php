<?php /** @noinspection SqlDialectInspection */

namespace JasperFW\QueryBuilderTest;

use JasperFW\QueryBuilder\Query;
use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase
{
    public function testSelectQuery()
    {
        $sut = new Query();
        $sut->select();
        $this->assertEquals(
            'SELECT {{columns}} FROM {{tables}} {{where}} {{sort}} {{pagination}}',
            $sut->getTemplate()
        );
    }

    public function testInsertQuery()
    {
        $sut = new Query();
        $sut->insert();
        $this->assertEquals('INSERT {{columns}} INTO {{tables}}', $sut->getTemplate());
    }

    public function testUpdateQuery()
    {
        $sut = new Query();
        $sut->update();
        $this->assertEquals('UPDATE {{table}} SET {{columns}} {{where}}', $sut->getTemplate());
    }

    public function testDeleteQuery()
    {
        $sut = new Query();
        $sut->delete();
        $this->assertEquals('DELETE FROM {{table}} {{where}}', $sut->getTemplate());
    }

    public function testCustomQuery()
    {
        $sut = new Query();
        $sut->template('your query here');
        $this->assertEquals('your query here', $sut->getTemplate());
    }

    public function testAddTable()
    {
        $sut = new Query();
        $sut->select()
            ->table('schema.tblA', 'tblA')
            ->join('schema.tblB', 'b')
            ->leftJoin('schema.tblC', 'c', 'tblA.index = c.index')
            ->rightJoin('schema.tbld', 'd', 'tblA.index = d.index')
            ->innerJoin('schema.tblE', 'e', 'tblA.index = e.index')
            ->outerJoin('schema.tblF', 'f', 'tblA.index = f.index');
        $expectedSnippet = 'schema.tblA tblA, schema.tblB b, schema.tblC c ON tblA.index = c.index, schema.tbld d ON tblA.index = d.index, schema.tblE e ON tblA.index = e.index, schema.tblF f ON tblA.index = f.index';
        $this->assertEquals($expectedSnippet, $sut->generateTables());
        $sut->insert();
        $this->assertEquals('schema.tblA', $sut->generateTables());
        $sut->update();
        $this->assertEquals('schema.tblA', $sut->generateTables());
        $sut->delete();
        $this->assertEquals('', $sut->generateTables());
    }

    public function testAddColumn()
    {
        $sut = new Query();
        $sut->select()
            ->column('table.colA', 'colA', 'bob', 'param')
            ->column('table.colB', 'colB', 'steve')
            ->column('table.colC', null, 'dave');
        $this->assertEquals('bob', $sut->getParameter('param'));
        $this->assertEquals('steve', $sut->getParameter('colB'));
        $this->assertEquals('dave', $sut->getParameter('tablecolC'));
        $this->assertEquals(
            '[table.colA] AS colA, [table.colB] AS colB, [table.colC]',
            $sut->generateColumns()
        );
        $sut->insert();
        $this->assertEquals(
            '[table.colA] = :param, [table.colB] = :colB, [table.colC] = :tablecolC',
            $sut->generateColumns()
        );
        $sut->update();
        $this->assertEquals(
            '[table.colA] = :param, [table.colB] = :colB, [table.colC] = :tablecolC',
            $sut->generateColumns()
        );
        $sut->delete();
        $this->assertEquals('', $sut->generateColumns());
    }

    public function testParameters()
    {
        $sut = new Query();
        $sut->parameter('name', 'value');
        $this->assertEquals('value', $sut->getParameter('name'));
        $sut->parameter('name', 'newValue');
        $this->assertEquals('newValue', $sut->getParameter('name'));
        $sut->parameter('sanitize me', 'value');
        $this->assertEquals('value', $sut->getParameter('sanitizeme'));
        $expectedParameterArray = [
            ':name' => 'newValue',
            ':sanitizeme' => 'value',
        ];
        $this->assertEquals($expectedParameterArray, $sut->getParameters());
    }

    public function testGeneratePaging()
    {
        $sut = new Query();
        $sut->pageNumber(2)
            ->pageSize(50);
        $this->assertEquals('LIMIT 50, 50', $sut->generatePagination());
    }

    public function testGenerateSort()
    {
        $sut = new Query();
        $sut->sortBy('colA', 'ASC')
            ->sortBy('colB');
        $this->assertEquals(', colA ASC,colB ASC', $sut->generateSort(','));
    }

    public function testGenerateWhere()
    {
        $sut = new Query();
        $sut->where('test test', ['a' => 'b']);
        $this->assertEquals('WHERE test test', $sut->generateWhere());
        $this->assertEquals([':a' => 'b'], $sut->getParameters());
    }

    public function testGenerateQuery()
    {
        $sut = new Query();
        $sut->select()
            ->table('schema.tblA', 'tblA')
            ->join('schema.tblB', 'b')
            ->leftJoin('schema.tblC', 'c', 'tblA.index = c.index')
            ->rightJoin('schema.tbld', 'd', 'tblA.index = d.index')
            ->innerJoin('schema.tblE', 'e', 'tblA.index = e.index')
            ->outerJoin('schema.tblF', 'f', 'tblA.index = f.index')
            ->column('table.colA', 'colA', 'bob', 'param')
            ->column('table.colB', 'colB', 'steve')
            ->column('table.colC', null, 'dave')
            ->where('test test', ['a' => 'b'])
            ->sortBy('colA', 'ASC')
            ->pageNumber(2)
            ->pageSize(50);
        $expectedQuery = <<<SQL
SELECT [table.colA] AS colA, [table.colB] AS colB, [table.colC] FROM schema.tblA tblA, schema.tblB b, schema.tblC c ON tblA.index = c.index, schema.tbld d ON tblA.index = d.index, schema.tblE e ON tblA.index = e.index, schema.tblF f ON tblA.index = f.index WHERE test test ORDER BY colA ASC LIMIT 50, 50
SQL;
        $this->assertEquals($expectedQuery, $sut->generateQuery());
    }
}
