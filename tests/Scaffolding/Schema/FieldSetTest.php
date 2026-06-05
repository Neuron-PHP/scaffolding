<?php

namespace Tests\Scaffolding\Schema;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Schema\Field;
use Neuron\Scaffolding\Schema\FieldSet;

class FieldSetTest extends TestCase
{
	public function testFromDefinitionParsesTypesAndLengths(): void
	{
		$set = FieldSet::fromDefinition( 'title:string,body:text,published:boolean,name:string:120' );

		$fields = $set->all();
		$this->assertCount( 4, $fields );

		$this->assertEquals( 'title', $fields[0]->name );
		$this->assertEquals( 'string', $fields[0]->type );
		$this->assertEquals( 255, $fields[0]->length );

		$this->assertEquals( 'text', $fields[1]->type );
		$this->assertTrue( $fields[1]->nullable );

		$this->assertEquals( 'boolean', $fields[2]->type );

		$this->assertEquals( 120, $fields[3]->length );
	}

	public function testNormalizeType(): void
	{
		$this->assertEquals( 'integer', FieldSet::normalizeType( 'int' ) );
		$this->assertEquals( 'biginteger', FieldSet::normalizeType( 'bigint' ) );
		$this->assertEquals( 'datetime', FieldSet::normalizeType( 'timestamp' ) );
		$this->assertEquals( 'json', FieldSet::normalizeType( 'jsonb' ) );
		$this->assertEquals( 'string', FieldSet::normalizeType( 'mystery' ) );
	}

	public function testPrimaryAndEditableAndListable(): void
	{
		$set = new FieldSet( [
			new Field( 'id', 'integer', isPrimary: true, autoIncrement: true ),
			new Field( 'title', 'string' ),
			new Field( 'body', 'text', nullable: true ),
			new Field( 'created_at', 'datetime', nullable: true ),
			new Field( 'updated_at', 'datetime', nullable: true ),
		] );

		$this->assertNotNull( $set->primary() );
		$this->assertEquals( 'id', $set->primary()->name );

		// editable() excludes the primary key and timestamps.
		$editable = array_map( fn( Field $f ) => $f->name, $set->editable() );
		$this->assertEquals( [ 'title', 'body' ], $editable );

		// listable() excludes textarea fields.
		$listable = array_map( fn( Field $f ) => $f->name, $set->listable() );
		$this->assertEquals( [ 'title' ], $listable );
	}
}
