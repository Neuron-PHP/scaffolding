<?php

namespace Tests\Scaffolding\Schema;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Schema\Field;

class FieldTest extends TestCase
{
	public function testNamingDerivations(): void
	{
		$field = new Field( name: 'author_id', type: 'integer' );

		$this->assertEquals( 'authorId', $field->camelName() );
		$this->assertEquals( 'AuthorId', $field->studlyName() );
		$this->assertEquals( '_authorId', $field->propertyName() );
		$this->assertEquals( 'getAuthorId', $field->getter() );
		$this->assertEquals( 'setAuthorId', $field->setter() );
		$this->assertEquals( 'Author Id', $field->label() );
	}

	public function testPhpTypeNullability(): void
	{
		$this->assertEquals( 'string', new Field( 'title', 'string' )->phpType() );
		$this->assertEquals( '?string', new Field( 'title', 'string', nullable: true )->phpType() );
		$this->assertEquals( 'int', new Field( 'count', 'integer' )->phpType() );
		$this->assertEquals( 'bool', new Field( 'active', 'boolean' )->phpType() );

		// Date/time is always nullable.
		$this->assertEquals( '?\DateTimeImmutable', new Field( 'created_at', 'datetime' )->phpType() );

		// Auto-increment primary keys are nullable.
		$this->assertEquals( '?int', new Field( 'id', 'integer', isPrimary: true, autoIncrement: true )->phpType() );
	}

	public function testDefaultLiterals(): void
	{
		$this->assertEquals( "''", new Field( 'title', 'string' )->phpDefaultLiteral() );
		$this->assertEquals( '0', new Field( 'count', 'integer' )->phpDefaultLiteral() );
		$this->assertEquals( 'false', new Field( 'active', 'boolean' )->phpDefaultLiteral() );
		$this->assertEquals( 'null', new Field( 'bio', 'text', nullable: true )->phpDefaultLiteral() );
		$this->assertEquals( 'null', new Field( 'created_at', 'datetime' )->phpDefaultLiteral() );
	}

	public function testDtoTypeMapping(): void
	{
		$this->assertEquals( 'string', new Field( 'title', 'string' )->dtoType() );
		$this->assertEquals( 'integer', new Field( 'count', 'integer' )->dtoType() );
		$this->assertEquals( 'boolean', new Field( 'active', 'boolean' )->dtoType() );
		$this->assertEquals( 'date_time', new Field( 'created_at', 'datetime' )->dtoType() );
		$this->assertEquals( 'email', new Field( 'email', 'email' )->dtoType() );
		// uuid is not a DTO validator type; falls back to string.
		$this->assertEquals( 'string', new Field( 'guid', 'uuid' )->dtoType() );
	}

	public function testHtmlInputType(): void
	{
		$this->assertEquals( 'text', new Field( 'title', 'string' )->htmlInputType() );
		$this->assertEquals( 'number', new Field( 'count', 'integer' )->htmlInputType() );
		$this->assertEquals( 'checkbox', new Field( 'active', 'boolean' )->htmlInputType() );
		$this->assertEquals( 'email', new Field( 'email', 'email' )->htmlInputType() );
		$this->assertTrue( new Field( 'body', 'text' )->isTextarea() );
	}

	public function testFromArrayAndToArrayExpressions(): void
	{
		$this->assertEquals( "(int)\$data['id']", new Field( 'id', 'integer' )->fromArrayExpr( "\$data['id']" ) );
		$this->assertStringContainsString( 'DateTimeImmutable', new Field( 'created_at', 'datetime' )->fromArrayExpr( "\$data['created_at']" ) );
		$this->assertStringContainsString( "format( 'Y-m-d H:i:s' )", new Field( 'created_at', 'datetime' )->toArrayExpr() );
	}
}
