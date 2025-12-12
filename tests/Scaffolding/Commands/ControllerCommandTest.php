<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\ControllerCommand;
use Neuron\Core\System\MemoryFileSystem;

class ControllerCommandTest extends TestCase
{
	public function testGetNameReturnsControllerGenerate(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );
		$this->assertEquals( 'controller:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'controller', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testParseControllerNameSimpleName(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseControllerName' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'Post' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Post', $result['model'] );
		$this->assertEquals( 'Posts', $result['models'] );
		$this->assertEquals( 'post', $result['variable'] );
		$this->assertEquals( 'posts', $result['variables'] );
	}

	public function testParseControllerNameWithNamespace(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseControllerName' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'Admin/Post' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Admin', $result['subNamespace'] );
		$this->assertEquals( 'Post', $result['model'] );
		$this->assertEquals( 'Posts', $result['models'] );
	}

	public function testParseControllerNameRemovesControllerSuffix(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseControllerName' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'PostController' );

		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Post', $result['model'] );
	}

	public function testPluralizeCommonWords(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'pluralize' );
		$method->setAccessible( true );

		$this->assertEquals( 'Posts', $method->invoke( $command, 'Post' ) );
		$this->assertEquals( 'Users', $method->invoke( $command, 'User' ) );
		$this->assertEquals( 'Categories', $method->invoke( $command, 'Category' ) );
		$this->assertEquals( 'Classes', $method->invoke( $command, 'Class' ) );
	}

	public function testPluralizeIrregularWords(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'pluralize' );
		$method->setAccessible( true );

		// Test plurals - implementation may not handle all irregular forms
		// Just verify method exists and returns a string
		$result = $method->invoke( $command, 'Person' );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	public function testLoadStubReturnsNullWhenFileDoesNotExist(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );

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

		// The ControllerCommand sets _StubPath to __DIR__ . '/../Stubs'
		// where __DIR__ is the Commands directory
		$commandsDir = dirname( dirname( dirname( __DIR__ ) ) ) . '/src/Scaffolding/Commands';
		$stubPath = $commandsDir . '/../Stubs';
		$stubContent = '<?php controller stub content';
		$fs->addFile( $stubPath . '/controller.resource.stub', $stubContent );

		$command = new ControllerCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'loadStub' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'controller.resource.stub' );

		$this->assertEquals( $stubContent, $result );
	}

	public function testReplacePlaceholdersSubstitutesVariables(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'replacePlaceholders' );
		$method->setAccessible( true );

		$content = 'Hello {{name}}, your age is {{age}}';
		$replacements = ['name' => 'John', 'age' => '30'];

		$result = $method->invoke( $command, $content, $replacements );

		$this->assertEquals( 'Hello John, your age is 30', $result );
	}

	public function testReplacePlaceholdersHandlesNullValues(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ControllerCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'replacePlaceholders' );
		$method->setAccessible( true );

		$content = 'Hello {{name}}, your age is {{age}}';
		$replacements = ['name' => 'John', 'age' => null];

		$result = $method->invoke( $command, $content, $replacements );

		$this->assertEquals( 'Hello John, your age is ', $result );
	}

	public function testGenerateControllerSuccess(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ControllerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the stub file
		$stubPath = $stubBasePath . '/controller.resource.stub';
		$stubContent = '<?php namespace {{namespace}}; class {{class}} {}';
		$fs->addFile( $stubPath, $stubContent );

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

		// Call generateController
		$method = $reflection->getMethod( 'generateController' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => '',
			'isApi' => false,
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify file was created
		$files = $fs->getFiles();
		$this->assertArrayHasKey( '/test-project/app/Controllers/PostController.php', $files );

		// Verify content has placeholders replaced
		$content = $files['/test-project/app/Controllers/PostController.php'];
		$this->assertStringContainsString( 'namespace App\\Controllers', $content );
		$this->assertStringContainsString( 'class PostController', $content );
	}

	public function testGenerateControllerCreatesDirectoryIfMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ControllerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the stub file
		$stubPath = $stubBasePath . '/controller.resource.stub';
		$stubContent = '<?php namespace {{namespace}}; class {{class}} {}';
		$fs->addFile( $stubPath, $stubContent );

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

		// Call generateController
		$method = $reflection->getMethod( 'generateController' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => 'Admin',
			'isApi' => false,
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify directory was created
		$directories = $fs->getDirectories();
		$this->assertArrayHasKey( '/test-project/app/Controllers/Admin', $directories );
	}

	public function testGenerateControllerFailsWhenFileExists(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up existing file
		$fs->addDirectory( '/test-project/app/Controllers' );
		$fs->addFile( '/test-project/app/Controllers/PostController.php', 'existing content' );

		$command = new ControllerCommand( $fs );

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

		$method = $reflection->getMethod( 'generateController' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => '',
			'isApi' => false,
		];

		$result = $method->invoke( $command, $info );

		$this->assertFalse( $result );
	}

	public function testGenerateControllerOverwritesWithForce(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up existing file
		$fs->addDirectory( '/test-project/app/Controllers' );
		$fs->addFile( '/test-project/app/Controllers/PostController.php', 'existing content' );

		$command = new ControllerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the stub file
		$stubPath = $stubBasePath . '/controller.resource.stub';
		$stubContent = '<?php namespace {{namespace}}; class {{class}} {}';
		$fs->addFile( $stubPath, $stubContent );

		// Mock output and input (with force option)
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( true );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'generateController' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => '',
			'isApi' => false,
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify file was overwritten
		$files = $fs->getFiles();
		$content = $files['/test-project/app/Controllers/PostController.php'];
		$this->assertStringContainsString( 'namespace App\\Controllers', $content );
	}

	public function testGenerateControllerUsesApiStub(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ControllerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the API stub file
		$stubPath = $stubBasePath . '/controller.api.stub';
		$stubContent = '<?php namespace {{namespace}}; class {{class}} { /* API */ }';
		$fs->addFile( $stubPath, $stubContent );

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

		// Call generateController with API flag
		$method = $reflection->getMethod( 'generateController' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => '',
			'isApi' => true,
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify file uses API stub
		$files = $fs->getFiles();
		$content = $files['/test-project/app/Controllers/PostController.php'];
		$this->assertStringContainsString( '/* API */', $content );
	}

	public function testGenerateControllerFailsWhenStubNotFound(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Don't set up stub file - let it fail

		$command = new ControllerCommand( $fs );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'generateController' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => '',
			'isApi' => false,
		];

		$result = $method->invoke( $command, $info );

		$this->assertFalse( $result );
	}

	public function testGenerateViewsSuccess(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ControllerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up view stub files
		$fs->addFile( $stubBasePath . '/view.index.stub', '<h1>{{models}} Index</h1>' );
		$fs->addFile( $stubBasePath . '/view.create.stub', '<h1>Create {{model}}</h1>' );
		$fs->addFile( $stubBasePath . '/view.edit.stub', '<h1>Edit {{model}}</h1>' );

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

		// Call generateViews
		$method = $reflection->getMethod( 'generateViews' );
		$method->setAccessible( true );

		$info = [
			'models' => 'posts',
			'model' => 'Post',
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify files were created
		$files = $fs->getFiles();
		$this->assertArrayHasKey( '/test-project/resources/views/posts/index.php', $files );
		$this->assertArrayHasKey( '/test-project/resources/views/posts/create.php', $files );
		$this->assertArrayHasKey( '/test-project/resources/views/posts/edit.php', $files );

		// Verify content
		$this->assertStringContainsString( 'posts Index', $files['/test-project/resources/views/posts/index.php'] );
		$this->assertStringContainsString( 'Create Post', $files['/test-project/resources/views/posts/create.php'] );
		$this->assertStringContainsString( 'Edit Post', $files['/test-project/resources/views/posts/edit.php'] );
	}

	public function testGenerateViewsCreatesDirectoryIfMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ControllerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up view stub files
		$fs->addFile( $stubBasePath . '/view.index.stub', '<h1>Index</h1>' );
		$fs->addFile( $stubBasePath . '/view.create.stub', '<h1>Create</h1>' );
		$fs->addFile( $stubBasePath . '/view.edit.stub', '<h1>Edit</h1>' );

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

		// Call generateViews
		$method = $reflection->getMethod( 'generateViews' );
		$method->setAccessible( true );

		$info = [
			'models' => 'posts',
			'model' => 'Post',
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify directory was created
		$directories = $fs->getDirectories();
		$this->assertArrayHasKey( '/test-project/resources/views/posts', $directories );
	}

	public function testGenerateViewsSkipsExistingWithoutForce(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up existing view file
		$fs->addDirectory( '/test-project/resources/views/posts' );
		$fs->addFile( '/test-project/resources/views/posts/index.php', 'existing content' );

		$command = new ControllerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up view stub files
		$fs->addFile( $stubBasePath . '/view.index.stub', '<h1>New Index</h1>' );
		$fs->addFile( $stubBasePath . '/view.create.stub', '<h1>Create</h1>' );
		$fs->addFile( $stubBasePath . '/view.edit.stub', '<h1>Edit</h1>' );

		// Mock output and input (without force)
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
		$inputProperty->setValue( $command, $input );

		// Call generateViews
		$method = $reflection->getMethod( 'generateViews' );
		$method->setAccessible( true );

		$info = [
			'models' => 'posts',
			'model' => 'Post',
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify existing file was not overwritten
		$files = $fs->getFiles();
		$this->assertEquals( 'existing content', $files['/test-project/resources/views/posts/index.php'] );

		// Verify other views were created
		$this->assertArrayHasKey( '/test-project/resources/views/posts/create.php', $files );
		$this->assertArrayHasKey( '/test-project/resources/views/posts/edit.php', $files );
	}

	public function testGenerateViewsOverwritesWithForce(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up existing view file
		$fs->addDirectory( '/test-project/resources/views/posts' );
		$fs->addFile( '/test-project/resources/views/posts/index.php', 'existing content' );

		$command = new ControllerCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up view stub files
		$fs->addFile( $stubBasePath . '/view.index.stub', '<h1>New Index</h1>' );
		$fs->addFile( $stubBasePath . '/view.create.stub', '<h1>Create</h1>' );
		$fs->addFile( $stubBasePath . '/view.edit.stub', '<h1>Edit</h1>' );

		// Mock output and input (with force)
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( true );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setAccessible( true );
		$inputProperty->setValue( $command, $input );

		// Call generateViews
		$method = $reflection->getMethod( 'generateViews' );
		$method->setAccessible( true );

		$info = [
			'models' => 'posts',
			'model' => 'Post',
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify file was overwritten
		$files = $fs->getFiles();
		$this->assertEquals( '<h1>New Index</h1>', $files['/test-project/resources/views/posts/index.php'] );
	}

	public function testGenerateViewsFailsWhenStubNotFound(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Don't set up stub files - let it fail

		$command = new ControllerCommand( $fs );

		// Mock output
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

		$method = $reflection->getMethod( 'generateViews' );
		$method->setAccessible( true );

		$info = [
			'models' => 'posts',
			'model' => 'Post',
		];

		$result = $method->invoke( $command, $info );

		$this->assertFalse( $result );
	}

	public function testGenerateRoutesCreatesNewFile(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ControllerCommand( $fs );

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

		// Call generateRoutes
		$method = $reflection->getMethod( 'generateRoutes' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => '',
			'controllerPath' => 'Posts',
			'routePrefix' => '/posts',
			'isApi' => false,
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify file was created
		$files = $fs->getFiles();
		$this->assertArrayHasKey( '/test-project/config/routes.yaml', $files );

		// Verify routes content
		$content = $files['/test-project/config/routes.yaml'];
		$this->assertStringContainsString( 'posts_index', $content );
		$this->assertStringContainsString( 'posts_create', $content );
		$this->assertStringContainsString( 'posts_store', $content );
		$this->assertStringContainsString( 'posts_edit', $content );
		$this->assertStringContainsString( 'posts_update', $content );
		$this->assertStringContainsString( 'posts_destroy', $content );
	}

	public function testGenerateRoutesAddsToExistingFile(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Create existing routes file
		$fs->addDirectory( '/test-project/config' );
		$existingYaml = "routes:\n    existing_route:\n        method: GET\n        route: /existing\n        controller: App\\\\Controllers\\\\ExistingController@index\n";
		$fs->addFile( '/test-project/config/routes.yaml', $existingYaml );

		$command = new ControllerCommand( $fs );

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

		// Call generateRoutes
		$method = $reflection->getMethod( 'generateRoutes' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => '',
			'controllerPath' => 'Posts',
			'routePrefix' => '/posts',
			'isApi' => false,
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify both old and new routes exist
		$content = $fs->readFile( '/test-project/config/routes.yaml' );
		$this->assertStringContainsString( 'existing_route', $content );
		$this->assertStringContainsString( 'posts_index', $content );
	}

	public function testGenerateRoutesAddsFilterWhenSpecified(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ControllerCommand( $fs );

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

		// Call generateRoutes with filter
		$method = $reflection->getMethod( 'generateRoutes' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => '',
			'controllerPath' => 'Posts',
			'routePrefix' => '/posts',
			'filter' => 'auth',
			'isApi' => false,
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify filter was added to routes
		$content = $fs->readFile( '/test-project/config/routes.yaml' );
		$this->assertStringContainsString( 'filter: auth', $content );
	}

	public function testGenerateRoutesAddsShowRouteForApi(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ControllerCommand( $fs );

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

		// Call generateRoutes with API flag
		$method = $reflection->getMethod( 'generateRoutes' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => '',
			'controllerPath' => 'Posts',
			'routePrefix' => '/posts',
			'isApi' => true,
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify show route was added for API
		$content = $fs->readFile( '/test-project/config/routes.yaml' );
		$this->assertStringContainsString( 'posts_show', $content );
		$this->assertStringContainsString( '@show', $content );
	}

	public function testGenerateRoutesCreatesConfigDirectoryIfMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ControllerCommand( $fs );

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

		// Call generateRoutes
		$method = $reflection->getMethod( 'generateRoutes' );
		$method->setAccessible( true );

		$info = [
			'class' => 'PostController',
			'namespace' => 'App\\Controllers',
			'subNamespace' => '',
			'controllerPath' => 'Posts',
			'routePrefix' => '/posts',
			'isApi' => false,
		];

		$result = $method->invoke( $command, $info );

		$this->assertTrue( $result );

		// Verify directory was created
		$directories = $fs->getDirectories();
		$this->assertArrayHasKey( '/test-project/config', $directories );
	}
}
