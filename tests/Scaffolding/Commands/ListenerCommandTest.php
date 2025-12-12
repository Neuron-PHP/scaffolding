<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\ListenerCommand;
use Neuron\Core\System\MemoryFileSystem;

class ListenerCommandTest extends TestCase
{
	public function testGetNameReturnsListenerGenerate(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ListenerCommand( $fs );
		$this->assertEquals( 'listener:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ListenerCommand( $fs );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'listener', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ListenerCommand( $fs );

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testGenerateListenerSuccess(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ListenerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the stub file
		$stubPath = $stubBasePath . '/listener.stub';
		$stubContent = '<?php namespace {{namespace}}; class {{class}} { public function handle({{eventClass}} ${{eventName}}) {} }';
		$fs->addFile( $stubPath, $stubContent );


		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateListener' );
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

		$result = $method->invoke( $command, 'SendWelcomeEmail', 'App\\Listeners', 'App\\Events\\UserRegistered' );

		$this->assertTrue( $result );

		// Verify file was created
		$files = $fs->getFiles();
		$this->assertArrayHasKey( '/test-project/app/Listeners/SendWelcomeEmail.php', $files );

		// Verify content has placeholders replaced
		$content = $files['/test-project/app/Listeners/SendWelcomeEmail.php'];
		$this->assertStringContainsString( 'namespace App\\Listeners', $content );
		$this->assertStringContainsString( 'class SendWelcomeEmail', $content );
		$this->assertStringContainsString( 'App\\Events\\UserRegistered', $content );
		$this->assertStringContainsString( '$UserRegistered', $content );
	}

	public function testGenerateListenerCreatesDirectoryIfMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ListenerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the stub file
		$stubPath = $stubBasePath . '/listener.stub';
		$stubContent = '<?php namespace {{namespace}}; class {{class}} {}';
		$fs->addFile( $stubPath, $stubContent );


		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateListener' );
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

		$result = $method->invoke( $command, 'TestListener', 'App\\Listeners', 'App\\Events\\TestEvent' );

		$this->assertTrue( $result );

		// Verify directory was created
		$directories = $fs->getDirectories();
		$this->assertArrayHasKey( '/test-project/app/Listeners', $directories );
	}

	public function testGenerateListenerFailsWhenFileExists(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up existing file
		$fs->addDirectory( '/test-project/app/Listeners' );
		$fs->addFile( '/test-project/app/Listeners/TestListener.php', 'existing content' );

		$command = new ListenerCommand( $fs );

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

		$method = $reflection->getMethod( 'generateListener' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'TestListener', 'App\\Listeners', 'App\\Events\\TestEvent' );

		$this->assertFalse( $result );
	}

	public function testGenerateListenerFailsWhenStubNotFound(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Don't set up stub file - let it fail

		$command = new ListenerCommand( $fs );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'generateListener' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'TestListener', 'App\\Listeners', 'App\\Events\\TestEvent' );

		$this->assertFalse( $result );
	}

	public function testUpdateEventListenersCreatesNewEventEntry(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Create config directory
		$fs->addDirectory( '/test-project/config' );

		$command = new ListenerCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'updateEventListeners' );
		$method->setAccessible( true );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$result = $method->invoke( $command, 'App\\Events\\UserRegistered', 'App\\Listeners\\SendWelcomeEmail' );

		$this->assertTrue( $result );

		// Verify file was created
		$files = $fs->getFiles();
		$this->assertArrayHasKey( '/test-project/config/event-listeners.yaml', $files );

		// Verify YAML content
		$content = $files['/test-project/config/event-listeners.yaml'];
		$this->assertStringContainsString( 'userRegistered', $content );
		$this->assertStringContainsString( 'App\\Events\\UserRegistered', $content );
		$this->assertStringContainsString( 'App\\Listeners\\SendWelcomeEmail', $content );
	}

	public function testUpdateEventListenersAddsListenerToExistingEvent(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Create existing event-listeners.yaml
		$fs->addDirectory( '/test-project/config' );
		$existingYaml = "events:\n    userRegistered:\n        class: App\\Events\\UserRegistered\n        listeners:\n            - App\\Listeners\\ExistingListener\n";
		$fs->addFile( '/test-project/config/event-listeners.yaml', $existingYaml );

		$command = new ListenerCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'updateEventListeners' );
		$method->setAccessible( true );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$result = $method->invoke( $command, 'App\\Events\\UserRegistered', 'App\\Listeners\\SendWelcomeEmail' );

		$this->assertTrue( $result );

		// Verify both listeners are present
		$content = $fs->readFile( '/test-project/config/event-listeners.yaml' );
		$this->assertStringContainsString( 'App\\Listeners\\ExistingListener', $content );
		$this->assertStringContainsString( 'App\\Listeners\\SendWelcomeEmail', $content );
	}

	public function testUpdateEventListenersHandlesDuplicateListener(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Create existing event-listeners.yaml with listener already registered
		$fs->addDirectory( '/test-project/config' );
		$existingYaml = "events:\n    userRegistered:\n        class: App\\Events\\UserRegistered\n        listeners:\n            - App\\Listeners\\SendWelcomeEmail\n";
		$fs->addFile( '/test-project/config/event-listeners.yaml', $existingYaml );

		$command = new ListenerCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'updateEventListeners' );
		$method->setAccessible( true );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$result = $method->invoke( $command, 'App\\Events\\UserRegistered', 'App\\Listeners\\SendWelcomeEmail' );

		$this->assertTrue( $result );

		// Verify listener appears only once
		$content = $fs->readFile( '/test-project/config/event-listeners.yaml' );
		$count = substr_count( $content, 'App\\Listeners\\SendWelcomeEmail' );
		$this->assertEquals( 1, $count );
	}

	public function testGenerateEventKeyConvertsToLowerCamelCase(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ListenerCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateEventKey' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'App\\Events\\UserRegistered' );

		$this->assertEquals( 'userRegistered', $result );
	}

	public function testGetClassNameExtractsClassName(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ListenerCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'getClassName' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'App\\Events\\UserRegistered' );

		$this->assertEquals( 'UserRegistered', $result );
	}

	public function testLoadStubReturnsNullWhenFileDoesNotExist(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ListenerCommand( $fs );

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

		$command = new ListenerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the stub file
		$stubPath = $stubBasePath . '/listener.stub';
		$stubContent = '<?php stub content';
		$fs->addFile( $stubPath, $stubContent );


		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'loadStub' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'listener.stub' );

		$this->assertEquals( $stubContent, $result );
	}
}
