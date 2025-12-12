<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\EventCommand;
use Neuron\Core\System\MemoryFileSystem;

class EventCommandTest extends TestCase
{
	public function testGetNameReturnsEventGenerate(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EventCommand( $fs );
		$this->assertEquals( 'event:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EventCommand( $fs );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EventCommand( $fs );

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testParsePropertiesReturnsEmptyArrayWhenNoProperties(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EventCommand( $fs );

		$reflection = new \ReflectionClass( $command );

		// Mock input with no properties
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'getOption' )->willReturn( null );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'parseProperties' );
		$method->setAccessible( true );

		$result = $method->invoke( $command );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function testParsePropertiesParsesValidPropertyString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EventCommand( $fs );

		$reflection = new \ReflectionClass( $command );

		// Mock input with single property
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'getOption' )->willReturn( 'userId:int' );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'parseProperties' );
		$method->setAccessible( true );

		$result = $method->invoke( $command );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'userId', $result[0]['name'] );
		$this->assertEquals( 'int', $result[0]['type'] );
	}

	public function testParsePropertiesParsesMultipleProperties(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EventCommand( $fs );

		$reflection = new \ReflectionClass( $command );

		// Mock input with array of properties
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'getOption' )->willReturn( ['userId:int', 'email:string'] );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'parseProperties' );
		$method->setAccessible( true );

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

		$command = new EventCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the stub file
		$stubPath = $stubBasePath . '/event.stub';
		$stubContent = '<?php namespace {{namespace}}; class {{class}} { {{properties}} public function __construct({{constructorParams}}) { {{constructorBody}} } }';
		$fs->addFile( $stubPath, $stubContent );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateEvent' );
		$method->setAccessible( true );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
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

		$command = new EventCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the stub file
		$stubPath = $stubBasePath . '/event.stub';
		$stubContent = '<?php namespace {{namespace}}; class {{class}} {}';
		$fs->addFile( $stubPath, $stubContent );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateEvent' );
		$method->setAccessible( true );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
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

		$command = new EventCommand( $fs );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'generateEvent' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'TestEvent', 'App\\Events', [] );

		$this->assertFalse( $result );
	}

	public function testLoadStubReturnsNullWhenFileDoesNotExist(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EventCommand( $fs );

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

		$command = new EventCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the stub file
		$stubPath = $stubBasePath . '/event.stub';
		$stubContent = '<?php stub content';
		$fs->addFile( $stubPath, $stubContent );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'loadStub' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'event.stub' );

		$this->assertEquals( $stubContent, $result );
	}
}
