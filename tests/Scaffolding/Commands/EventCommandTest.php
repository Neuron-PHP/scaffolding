<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\EventCommand;
use Neuron\Core\System\MemoryFileSystem;
use Neuron\Scaffolding\Testing\MemoryTemplateEngine;

class EventCommandTest extends TestCase
{
	public function testGetNameReturnsEventGenerate(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EventCommand( $fs, $templates );
		$this->assertEquals( 'event:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EventCommand( $fs, $templates );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EventCommand( $fs, $templates );

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testParsePropertiesReturnsEmptyArrayWhenNoProperties(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EventCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );

		// Mock input with no properties
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'getOption' )->willReturn( null );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'parseProperties' );

		$result = $method->invoke( $command );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function testParsePropertiesParsesValidPropertyString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EventCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );

		// Mock input with single property
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'getOption' )->willReturn( 'userId:int' );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'parseProperties' );

		$result = $method->invoke( $command );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'userId', $result[0]['name'] );
		$this->assertEquals( 'int', $result[0]['type'] );
	}

	public function testParsePropertiesParsesMultipleProperties(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EventCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );

		// Mock input with array of properties
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'getOption' )->willReturn( ['userId:int', 'email:string'] );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'parseProperties' );

		$result = $method->invoke( $command );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'userId', $result[0]['name'] );
		$this->assertEquals( 'int', $result[0]['type'] );
		$this->assertEquals( 'email', $result[1]['name'] );
		$this->assertEquals( 'string', $result[1]['type'] );
	}

	public function testGenerateEventSuccess(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up template engine with stub content
		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'event.stub', '<?php namespace {{namespace}}; class {{class}} { {{properties}} public function __construct({{constructorParams}}) { {{constructorBody}} } }' );

		$command = new EventCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateEvent' );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$properties = [
			['name' => 'userId', 'type' => 'int'],
			['name' => 'email', 'type' => 'string']
		];

		$result = $method->invoke( $command, 'UserRegistered', 'App\\Events', $properties );

		$this->assertTrue( $result );

		// Verify file was created
		$files = $fs->getFiles();
		$this->assertArrayHasKey( '/test-project/app/Events/UserRegistered.php', $files );

		// Verify content has placeholders replaced
		$content = $files['/test-project/app/Events/UserRegistered.php'];
		$this->assertStringContainsString( 'namespace App\\Events', $content );
		$this->assertStringContainsString( 'class UserRegistered', $content );
		$this->assertStringContainsString( 'public int $userId', $content );
		$this->assertStringContainsString( 'public string $email', $content );
	}

	public function testGenerateEventCreatesDirectoryIfMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up template engine with stub content
		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'event.stub', '<?php namespace {{namespace}}; class {{class}} {}' );

		$command = new EventCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateEvent' );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$result = $method->invoke( $command, 'TestEvent', 'App\\Events', [] );

		$this->assertTrue( $result );

		// Verify directory was created
		$directories = $fs->getDirectories();
		$this->assertArrayHasKey( '/test-project/app/Events', $directories );
	}

	public function testGenerateEventFailsWhenFileExists(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up existing file
		$fs->addDirectory( '/test-project/app/Events' );
		$fs->addFile( '/test-project/app/Events/TestEvent.php', 'existing content' );

		$templates = new MemoryTemplateEngine();
		$command = new EventCommand( $fs, $templates );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'generateEvent' );

		$result = $method->invoke( $command, 'TestEvent', 'App\\Events', [] );

		$this->assertFalse( $result );
	}

	public function testGenerateEventFailsWhenDirectoryCreationFails(): void
	{
		// Create a mock filesystem that fails to create directories
		$fs = $this->createMock( \Neuron\Core\System\IFileSystem::class );
		$fs->method( 'getcwd' )->willReturn( '/test-project' );
		$fs->method( 'fileExists' )->willReturn( false );
		$fs->method( 'isDir' )->willReturn( false );
		$fs->method( 'mkdir' )->willReturn( false );

		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'event.stub', '<?php namespace {{namespace}}; class {{class}} {}' );

		$command = new EventCommand( $fs, $templates );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'generateEvent' );

		$result = $method->invoke( $command, 'TestEvent', 'App\\Events', [] );

		$this->assertFalse( $result );
	}

	public function testGenerateEventFailsWhenStubNotFound(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Don't add template to template engine - let it fail
		$templates = new MemoryTemplateEngine();

		$command = new EventCommand( $fs, $templates );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'generateEvent' );

		$result = $method->invoke( $command, 'TestEvent', 'App\\Events', [] );

		$this->assertFalse( $result );
	}

	public function testGenerateEventFailsWhenFileWriteFails(): void
	{
		// Create a mock filesystem that fails to write files
		$fs = $this->createMock( \Neuron\Core\System\IFileSystem::class );
		$fs->method( 'getcwd' )->willReturn( '/test-project' );
		$fs->method( 'fileExists' )->willReturn( false );
		$fs->method( 'isDir' )->willReturn( true );
		$fs->method( 'writeFile' )->willReturn( false );

		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'event.stub', '<?php namespace {{namespace}}; class {{class}} {}' );

		$command = new EventCommand( $fs, $templates );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'generateEvent' );

		$result = $method->invoke( $command, 'TestEvent', 'App\\Events', [] );

		$this->assertFalse( $result );
	}

	public function testParsePropertiesHandlesInvalidPropertyFormat(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EventCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );

		// Mock input with invalid property (missing type)
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'getOption' )->willReturn( ['userId', 'email:string'] );

		// Mock output to capture warnings
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'parseProperties' );

		$result = $method->invoke( $command );

		// Should only parse the valid property, skip the invalid one
		$this->assertCount( 1, $result );
		$this->assertEquals( 'email', $result[0]['name'] );
		$this->assertEquals( 'string', $result[0]['type'] );
	}

	public function testParsePropertiesTrimsWhitespace(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EventCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );

		// Mock input with properties that have whitespace
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'getOption' )->willReturn( ' userId : int ' );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'parseProperties' );

		$result = $method->invoke( $command );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'userId', $result[0]['name'] );
		$this->assertEquals( 'int', $result[0]['type'] );
	}
}


