<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\ScaffoldCommand;

class ScaffoldCommandTest extends TestCase
{
	public function testGetNameReturnsScaffoldGenerate(): void
	{
		$command = new ScaffoldCommand();
		$this->assertEquals( 'scaffold:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$command = new ScaffoldCommand();
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'scaffold', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new ScaffoldCommand();
		$command->configure();
		$this->assertTrue( true );
	}

	public function testParseResourceNameSimpleName(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseResourceName' );

		$result = $method->invoke( $command, 'Post' );

		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Post', $result['model'] );
		$this->assertEquals( 'Posts', $result['models'] );
		$this->assertEquals( 'post', $result['variable'] );
		$this->assertEquals( 'posts', $result['variables'] );
		$this->assertEquals( 'posts', $result['tableName'] );
	}

	public function testParseResourceNameWithNamespace(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseResourceName' );

		$result = $method->invoke( $command, 'Admin/Post' );

		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Admin', $result['subNamespace'] );
		$this->assertEquals( 'Post', $result['model'] );
		$this->assertEquals( 'Posts', $result['models'] );
		$this->assertEquals( 'posts', $result['tableName'] );
	}

	public function testParseResourceNameRemovesControllerSuffix(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseResourceName' );

		$result = $method->invoke( $command, 'PostController' );

		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Post', $result['model'] );
	}

	public function testParseFieldsEmptyString(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseFields' );

		$result = $method->invoke( $command, '' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function testParseFieldsSimpleTypes(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseFields' );

		$result = $method->invoke( $command, 'title:string,age:integer,active:boolean' );

		$this->assertCount( 3, $result );
		$this->assertEquals( 'title', $result[0]['name'] );
		$this->assertEquals( 'string', $result[0]['type'] );
		$this->assertEquals( 'age', $result[1]['name'] );
		$this->assertEquals( 'integer', $result[1]['type'] );
		$this->assertEquals( 'active', $result[2]['name'] );
		$this->assertEquals( 'boolean', $result[2]['type'] );
	}

	public function testMapFieldTypeVariants(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'mapFieldType' );

		$this->assertEquals( 'string', $method->invoke( $command, 'varchar' ) );
		$this->assertEquals( 'integer', $method->invoke( $command, 'int' ) );
		$this->assertEquals( 'boolean', $method->invoke( $command, 'bool' ) );
		$this->assertEquals( 'text', $method->invoke( $command, 'text' ) );
		$this->assertEquals( 'datetime', $method->invoke( $command, 'timestamp' ) );
		$this->assertEquals( 'string', $method->invoke( $command, 'unknown_type' ) );
	}

	public function testPluralizeCommonWords(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'pluralize' );

		$this->assertEquals( 'Posts', $method->invoke( $command, 'Post' ) );
		$this->assertEquals( 'Categories', $method->invoke( $command, 'Category' ) );
		$this->assertEquals( 'Boxes', $method->invoke( $command, 'Box' ) );
	}

	public function testUnderscoreConversion(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'underscore' );

		$this->assertEquals( 'posts', $method->invoke( $command, 'Posts' ) );
		$this->assertEquals( 'blog_posts', $method->invoke( $command, 'BlogPosts' ) );
	}

	public function testGetFieldOptionsForString(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'getFieldOptions' );

		$options = $method->invoke( $command, 'string', 'name' );

		$this->assertArrayHasKey( 'limit', $options );
		$this->assertEquals( 255, $options['limit'] );
	}

	public function testGenerateMigrationTemplateWithFields(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateMigrationTemplate' );

		$result = $method->invoke( $command, [ 'tableName' => 'posts' ], 'title:string,body:text,published:boolean' );

		$this->assertStringContainsString( 'posts', $result );
		$this->assertStringContainsString( 'title', $result );
		$this->assertStringContainsString( 'body', $result );
		$this->assertStringContainsString( 'published', $result );
	}

	public function testGenerateMigrationTemplateWithoutFields(): void
	{
		$command = new ScaffoldCommand();

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateMigrationTemplate' );

		$result = $method->invoke( $command, [ 'tableName' => 'posts' ], '' );

		$this->assertStringContainsString( 'posts', $result );
		$this->assertStringContainsString( '// Add your columns here', $result );
	}
}
