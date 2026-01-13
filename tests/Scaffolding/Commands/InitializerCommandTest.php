<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\InitializerCommand;
use Neuron\Core\System\MemoryFileSystem;
use Neuron\Scaffolding\Testing\MemoryTemplateEngine;

class InitializerCommandTest extends TestCase
{
	public function testGetNameReturnsInitializerGenerate(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new InitializerCommand( $fs, $templates );
		$this->assertEquals( 'initializer:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new InitializerCommand( $fs, $templates );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'initializer', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new InitializerCommand( $fs, $templates );

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testGenerateInitializerSuccess(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up template engine with stub content
		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'initializer.stub', '<?php namespace {{namespace}}; class {{class}} { public function run() {} }' );

		$command = new InitializerCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateInitializer' );
		$method->setAccessible( true );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$result = $method->invoke( $command, 'DatabaseInitializer', 'App\\Initializers' );

		$this->assertTrue( $result );

		// Verify file was created
		$files = $fs->getFiles();
		$this->assertArrayHasKey( '/test-project/app/Initializers/DatabaseInitializer.php', $files );

		// Verify content has placeholders replaced
		$content = $files['/test-project/app/Initializers/DatabaseInitializer.php'];
		$this->assertStringContainsString( 'namespace App\\Initializers', $content );
		$this->assertStringContainsString( 'class DatabaseInitializer', $content );
	}

	public function testGenerateInitializerCreatesDirectoryIfMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up template engine with stub content
		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'initializer.stub', '<?php namespace {{namespace}}; class {{class}} {}' );

		$command = new InitializerCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateInitializer' );
		$method->setAccessible( true );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$result = $method->invoke( $command, 'TestInitializer', 'App\\Initializers' );

		$this->assertTrue( $result );

		// Verify directory was created
		$directories = $fs->getDirectories();
		$this->assertArrayHasKey( '/test-project/app/Initializers', $directories );
	}

	public function testGenerateInitializerFailsWhenFileExists(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up existing file
		$fs->addDirectory( '/test-project/app/Initializers' );
		$fs->addFile( '/test-project/app/Initializers/TestInitializer.php', 'existing content' );

		$templates = new MemoryTemplateEngine();
		$command = new InitializerCommand( $fs, $templates );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false ); // Not forcing overwrite

		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'generateInitializer' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'TestInitializer', 'App\\Initializers' );

		$this->assertFalse( $result );
	}

	public function testGenerateInitializerFailsWhenStubNotFound(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Don't set up stub file - let it fail

		$templates = new MemoryTemplateEngine();
		$command = new InitializerCommand( $fs, $templates );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'generateInitializer' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'TestInitializer', 'App\\Initializers' );

		$this->assertFalse( $result );
	}

	public function testGenerateInitializerFailsWhenDirectoryCreationFails(): void
	{
		// Create a mock filesystem that fails to create directories
		$fs = $this->createMock( \Neuron\Core\System\IFileSystem::class );
		$fs->method( 'getcwd' )->willReturn( '/test-project' );
		$fs->method( 'fileExists' )->willReturn( false );
		$fs->method( 'isDir' )->willReturn( false );
		$fs->method( 'mkdir' )->willReturn( false );

		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'initializer.stub', '<?php namespace {{namespace}}; class {{class}} {}' );

		$command = new InitializerCommand( $fs, $templates );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );

		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'generateInitializer' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'TestInitializer', 'App\\Initializers' );

		$this->assertFalse( $result );
	}

	public function testGenerateInitializerFailsWhenFileWriteFails(): void
	{
		// Create a mock filesystem that fails to write files
		$fs = $this->createMock( \Neuron\Core\System\IFileSystem::class );
		$fs->method( 'getcwd' )->willReturn( '/test-project' );
		$fs->method( 'fileExists' )->willReturn( false );
		$fs->method( 'isDir' )->willReturn( true );
		$fs->method( 'writeFile' )->willReturn( false );

		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'initializer.stub', '<?php namespace {{namespace}}; class {{class}} {}' );

		$command = new InitializerCommand( $fs, $templates );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'generateInitializer' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'TestInitializer', 'App\\Initializers' );

		$this->assertFalse( $result );
	}

}
