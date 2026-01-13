<?php

namespace Tests\Scaffolding\Services;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Services\FileTemplateEngine;
use Neuron\Core\System\MemoryFileSystem;

class FileTemplateEngineTest extends TestCase
{
	public function testExistsReturnsTrueWhenFileExists(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Create stub directory and file
		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );
		$fs->addFile( $stubPath . '/test.stub', 'test content' );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$this->assertTrue( $engine->exists( 'test.stub' ) );
	}

	public function testExistsReturnsFalseWhenFileDoesNotExist(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$this->assertFalse( $engine->exists( 'nonexistent.stub' ) );
	}

	public function testLoadReturnsContentWhenFileExists(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );

		$expectedContent = '<?php namespace {{namespace}}; class {{class}} {}';
		$fs->addFile( $stubPath . '/controller.stub', $expectedContent );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$content = $engine->load( 'controller.stub' );

		$this->assertEquals( $expectedContent, $content );
	}

	public function testLoadReturnsNullWhenFileDoesNotExist(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$content = $engine->load( 'nonexistent.stub' );

		$this->assertNull( $content );
	}

	public function testLoadReturnsNullWhenFileReadFails(): void
	{
		// Create a custom mock filesystem that returns false on readFile
		$fs = $this->createMock( \Neuron\Core\System\IFileSystem::class );
		$fs->method( 'fileExists' )->willReturn( true );
		$fs->method( 'readFile' )->willReturn( false );

		$stubPath = '/test-project/stubs';
		$engine = new FileTemplateEngine( $fs, $stubPath );

		$content = $engine->load( 'test.stub' );

		$this->assertNull( $content );
	}

	public function testRenderReplacesPlaceholdersWithData(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );

		$template = '<?php namespace {{namespace}}; class {{class}} {}';
		$fs->addFile( $stubPath . '/controller.stub', $template );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$result = $engine->render( 'controller.stub', [
			'namespace' => 'App\\Controllers',
			'class' => 'PostController'
		] );

		$expected = '<?php namespace App\\Controllers; class PostController {}';
		$this->assertEquals( $expected, $result );
	}

	public function testRenderReplacesMultipleOccurrencesOfSamePlaceholder(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );

		$template = '{{var}} and {{var}} are the same as {{var}}';
		$fs->addFile( $stubPath . '/test.stub', $template );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$result = $engine->render( 'test.stub', ['var' => 'test'] );

		$this->assertEquals( 'test and test are the same as test', $result );
	}

	public function testRenderHandlesNullValues(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );

		$template = 'Hello {{name}}, your age is {{age}}';
		$fs->addFile( $stubPath . '/greeting.stub', $template );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$result = $engine->render( 'greeting.stub', [
			'name' => 'John',
			'age' => null
		] );

		$this->assertEquals( 'Hello John, your age is ', $result );
	}

	public function testRenderThrowsExceptionWhenTemplateNotFound(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Template not found: nonexistent.stub' );

		$engine->render( 'nonexistent.stub', [] );
	}

	public function testRenderWorksWithEmptyData(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );

		$template = 'No placeholders here';
		$fs->addFile( $stubPath . '/simple.stub', $template );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$result = $engine->render( 'simple.stub', [] );

		$this->assertEquals( 'No placeholders here', $result );
	}

	public function testRenderIgnoresUnusedPlaceholdersInData(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );

		$template = 'Hello {{name}}!';
		$fs->addFile( $stubPath . '/greeting.stub', $template );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$result = $engine->render( 'greeting.stub', [
			'name' => 'John',
			'extra' => 'unused'
		] );

		$this->assertEquals( 'Hello John!', $result );
	}

	public function testRenderLeavesUnmatchedPlaceholdersIntact(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$stubPath = '/test-project/stubs';
		$fs->addDirectory( $stubPath );

		$template = 'Hello {{name}}, you are {{age}} years old';
		$fs->addFile( $stubPath . '/greeting.stub', $template );

		$engine = new FileTemplateEngine( $fs, $stubPath );

		$result = $engine->render( 'greeting.stub', ['name' => 'John'] );

		$this->assertEquals( 'Hello John, you are {{age}} years old', $result );
	}
}
