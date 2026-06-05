<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\ControllerCommand;
use Neuron\Core\System\MemoryFileSystem;
use Neuron\Scaffolding\Testing\MemoryTemplateEngine;

class ControllerCommandTest extends TestCase
{
	public function testGetNameReturnsControllerGenerate(): void
	{
		$command = new ControllerCommand( new MemoryFileSystem(), new MemoryTemplateEngine() );
		$this->assertEquals( 'controller:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$command = new ControllerCommand( new MemoryFileSystem(), new MemoryTemplateEngine() );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertStringContainsString( 'controller', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new ControllerCommand( new MemoryFileSystem(), new MemoryTemplateEngine() );
		$command->configure();
		$this->assertTrue( true );
	}

	public function testParseControllerNameSimpleName(): void
	{
		$command = new ControllerCommand( new MemoryFileSystem(), new MemoryTemplateEngine() );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseControllerName' );

		$result = $method->invoke( $command, 'Post' );

		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Post', $result['model'] );
		$this->assertEquals( 'Posts', $result['models'] );
		$this->assertEquals( 'post', $result['variable'] );
		$this->assertEquals( 'posts', $result['variables'] );
		$this->assertEquals( 'posts', $result['tableName'] );
	}

	public function testParseControllerNameWithNamespace(): void
	{
		$command = new ControllerCommand( new MemoryFileSystem(), new MemoryTemplateEngine() );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseControllerName' );

		$result = $method->invoke( $command, 'Admin/Post' );

		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Admin', $result['subNamespace'] );
		$this->assertEquals( 'Post', $result['model'] );
		$this->assertEquals( 'Admin/Posts', $result['controllerPath'] );
		$this->assertEquals( '/admin/posts', $result['routePrefix'] );
	}

	public function testParseControllerNameRemovesControllerSuffix(): void
	{
		$command = new ControllerCommand( new MemoryFileSystem(), new MemoryTemplateEngine() );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseControllerName' );

		$result = $method->invoke( $command, 'PostController' );

		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Post', $result['model'] );
	}

	public function testPluralizeCommonWords(): void
	{
		$command = new ControllerCommand( new MemoryFileSystem(), new MemoryTemplateEngine() );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'pluralize' );

		$this->assertEquals( 'Posts', $method->invoke( $command, 'Post' ) );
		$this->assertEquals( 'Categories', $method->invoke( $command, 'Category' ) );
		$this->assertEquals( 'Classes', $method->invoke( $command, 'Class' ) );
	}
}
