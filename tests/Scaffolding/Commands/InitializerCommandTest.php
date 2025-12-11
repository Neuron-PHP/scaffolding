<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\InitializerCommand;
use Neuron\Core\System\MemoryFileSystem;

class InitializerCommandTest extends TestCase
{
	public function testGetNameReturnsInitializerGenerate(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InitializerCommand( $fs );
		$this->assertEquals( 'initializer:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InitializerCommand( $fs );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'initializer', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InitializerCommand( $fs );

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testGenerateInitializerSuccess(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up the stub file at the exact path with ../ that InitializerCommand uses
		$stubPath = '/Users/lee/projects/personal/neuron/scaffolding/src/Scaffolding/Commands/../Stubs/initializer.stub';
		$stubContent = '<?php namespace {{namespace}}; class {{class}} { public function run() {} }';
		$fs->addFile( $stubPath, $stubContent );

		$command = new InitializerCommand( $fs );

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

		// Set up the stub file at the exact path with ../ that InitializerCommand uses
		$stubPath = '/Users/lee/projects/personal/neuron/scaffolding/src/Scaffolding/Commands/../Stubs/initializer.stub';
		$stubContent = '<?php namespace {{namespace}}; class {{class}} {}';
		$fs->addFile( $stubPath, $stubContent );

		$command = new InitializerCommand( $fs );

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

		$command = new InitializerCommand( $fs );

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

		$command = new InitializerCommand( $fs );

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

	public function testLoadStubReturnsNullWhenFileDoesNotExist(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InitializerCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'loadStub' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'nonexistent.stub' );

		$this->assertNull( $result );
	}

	public function testLoadStubReturnsContentWhenFileExists(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up the stub file at the exact path with ../ that InitializerCommand uses
		$stubPath = '/Users/lee/projects/personal/neuron/scaffolding/src/Scaffolding/Commands/../Stubs/initializer.stub';
		$stubContent = '<?php stub content';
		$fs->addFile( $stubPath, $stubContent );

		$command = new InitializerCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'loadStub' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'initializer.stub' );

		$this->assertEquals( $stubContent, $result );
	}
}
