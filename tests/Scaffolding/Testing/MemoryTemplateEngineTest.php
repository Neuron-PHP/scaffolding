<?php

namespace Tests\Scaffolding\Testing;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Testing\MemoryTemplateEngine;

class MemoryTemplateEngineTest extends TestCase
{
	public function testAddTemplateStoresTemplate(): void
	{
		$engine = new MemoryTemplateEngine();
		$result = $engine->addTemplate( 'test.stub', 'content' );

		$this->assertInstanceOf( MemoryTemplateEngine::class, $result );
		$this->assertTrue( $engine->exists( 'test.stub' ) );
	}

	public function testExistsReturnsTrueForAddedTemplate(): void
	{
		$engine = new MemoryTemplateEngine();
		$engine->addTemplate( 'test.stub', 'content' );

		$this->assertTrue( $engine->exists( 'test.stub' ) );
	}

	public function testExistsReturnsFalseForNonExistentTemplate(): void
	{
		$engine = new MemoryTemplateEngine();

		$this->assertFalse( $engine->exists( 'nonexistent.stub' ) );
	}

	public function testLoadReturnsContentForAddedTemplate(): void
	{
		$engine = new MemoryTemplateEngine();
		$engine->addTemplate( 'test.stub', 'template content' );

		$this->assertEquals( 'template content', $engine->load( 'test.stub' ) );
	}

	public function testLoadReturnsNullForNonExistentTemplate(): void
	{
		$engine = new MemoryTemplateEngine();

		$this->assertNull( $engine->load( 'nonexistent.stub' ) );
	}

	public function testRenderReplacesPlaceholders(): void
	{
		$engine = new MemoryTemplateEngine();
		$engine->addTemplate( 'test.stub', 'Hello {{name}}, you are {{age}} years old' );

		$result = $engine->render( 'test.stub', [
			'name' => 'John',
			'age' => '30'
		] );

		$this->assertEquals( 'Hello John, you are 30 years old', $result );
	}

	public function testRenderThrowsExceptionForNonExistentTemplate(): void
	{
		$engine = new MemoryTemplateEngine();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Template not found: nonexistent.stub' );

		$engine->render( 'nonexistent.stub', [] );
	}

	public function testRenderHandlesNullValues(): void
	{
		$engine = new MemoryTemplateEngine();
		$engine->addTemplate( 'test.stub', 'Hello {{name}}' );

		$result = $engine->render( 'test.stub', ['name' => null] );

		$this->assertEquals( 'Hello ', $result );
	}

	public function testAddTemplateChaining(): void
	{
		$engine = new MemoryTemplateEngine();
		$result = $engine
			->addTemplate( 'first.stub', 'first' )
			->addTemplate( 'second.stub', 'second' );

		$this->assertInstanceOf( MemoryTemplateEngine::class, $result );
		$this->assertTrue( $engine->exists( 'first.stub' ) );
		$this->assertTrue( $engine->exists( 'second.stub' ) );
	}
}
