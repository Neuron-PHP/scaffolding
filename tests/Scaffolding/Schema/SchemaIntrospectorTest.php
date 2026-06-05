<?php

namespace Tests\Scaffolding\Schema;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Schema\SchemaIntrospector;
use PDO;

class SchemaIntrospectorTest extends TestCase
{
	private function makePdo(): PDO
	{
		$pdo = new PDO( 'sqlite::memory:' );
		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$pdo->exec(
			'CREATE TABLE jud_docket (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				case_number VARCHAR(50) NOT NULL,
				description TEXT,
				amount DECIMAL(10,2),
				is_closed BOOLEAN DEFAULT 0,
				hearing_date DATETIME
			)'
		);

		return $pdo;
	}

	public function testIntrospectSqliteTable(): void
	{
		$set = new SchemaIntrospector( $this->makePdo() )->introspect( 'jud_docket' );

		$fields = [];
		foreach( $set->all() as $field )
		{
			$fields[ $field->name ] = $field;
		}

		$this->assertArrayHasKey( 'id', $fields );
		$this->assertTrue( $fields['id']->isPrimary );
		$this->assertEquals( 'integer', $fields['id']->type );

		$this->assertEquals( 'string', $fields['case_number']->type );
		$this->assertEquals( 50, $fields['case_number']->length );
		$this->assertFalse( $fields['case_number']->nullable );

		$this->assertEquals( 'text', $fields['description']->type );
		$this->assertTrue( $fields['description']->nullable );

		$this->assertEquals( 'decimal', $fields['amount']->type );
		$this->assertEquals( 'boolean', $fields['is_closed']->type );
		$this->assertEquals( 'datetime', $fields['hearing_date']->type );
	}

	public function testIntrospectMissingTableThrows(): void
	{
		$this->expectException( \Exception::class );
		new SchemaIntrospector( $this->makePdo() )->introspect( 'does_not_exist' );
	}
}
