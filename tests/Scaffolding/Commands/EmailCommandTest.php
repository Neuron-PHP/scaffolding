<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\EmailCommand;
use Neuron\Core\System\MemoryFileSystem;

class EmailCommandTest extends TestCase
{
	public function testGetNameReturnsMailGenerate(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EmailCommand( $fs );
		$this->assertEquals( 'mail:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EmailCommand( $fs );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'email', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EmailCommand( $fs );

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testReplacePlaceholdersSubstitutesVariables(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EmailCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'replacePlaceholders' );
		$method->setAccessible( true );

		$content = 'Subject: {{title}} - Body: {{content}}';
		$replacements = ['title' => 'Welcome', 'content' => 'Hello World'];

		$result = $method->invoke( $command, $content, $replacements );

		$this->assertEquals( 'Subject: Welcome - Body: Hello World', $result );
	}

	public function testReplacePlaceholdersHandlesNullValues(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new EmailCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'replacePlaceholders' );
		$method->setAccessible( true );

		$content = 'Title: {{title}}, Content: {{content}}';
		$replacements = ['title' => 'Test', 'content' => null];

		$result = $method->invoke( $command, $content, $replacements );

		$this->assertEquals( 'Title: Test, Content: ', $result );
	}

	public function testCreateTemplateSuccess(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up the stub file (EmailCommand looks in component path for stub)
		$componentPath = dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) );
		$stubPath = $componentPath . '/src/Cms/Cli/Commands/Generate/stubs/email.stub';
		$stubContent = '<h1>{{title}}</h1><div>{{content}}</div>';
		$fs->addFile( $stubPath, $stubContent );

		// Create emails directory
		$fs->addDirectory( '/test-project/resources/views/emails' );

		$command = new EmailCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'createTemplate' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'welcome' );

		$this->assertTrue( $result );

		// Verify file was created
		$files = $fs->getFiles();
		$this->assertArrayHasKey( '/test-project/resources/views/emails/welcome.php', $files );

		// Verify content has placeholders replaced
		$content = $files['/test-project/resources/views/emails/welcome.php'];
		$this->assertStringContainsString( 'Welcome', $content );
	}

	public function testCreateTemplateCreatesDirectoryIfMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up the stub file
		$componentPath = dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) );
		$stubPath = $componentPath . '/src/Cms/Cli/Commands/Generate/stubs/email.stub';
		$stubContent = '<h1>{{title}}</h1>';
		$fs->addFile( $stubPath, $stubContent );

		// Don't create emails directory - let command create it

		$command = new EmailCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'createTemplate' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'test-email' );

		$this->assertTrue( $result );

		// Verify directory was created
		$directories = $fs->getDirectories();
		$this->assertArrayHasKey( '/test-project/resources/views/emails', $directories );
	}

	public function testCreateTemplateFailsWhenFileExists(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up existing file
		$fs->addDirectory( '/test-project/resources/views/emails' );
		$fs->addFile( '/test-project/resources/views/emails/welcome.php', 'existing content' );

		$command = new EmailCommand( $fs );

		// Mock output to prevent errors
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'createTemplate' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'welcome' );

		$this->assertFalse( $result );
	}

	public function testCreateTemplateFailsWhenStubNotFound(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Don't set up stub file - let it fail

		$command = new EmailCommand( $fs );

		// Mock output to prevent errors
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'createTemplate' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'welcome' );

		$this->assertFalse( $result );
	}

	public function testCreateTemplateHandlesHyphenatedNames(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up the stub file
		$componentPath = dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) );
		$stubPath = $componentPath . '/src/Cms/Cli/Commands/Generate/stubs/email.stub';
		$stubContent = '<h1>{{title}}</h1>';
		$fs->addFile( $stubPath, $stubContent );

		$fs->addDirectory( '/test-project/resources/views/emails' );

		$command = new EmailCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'createTemplate' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'password-reset' );

		$this->assertTrue( $result );

		// Verify the title was properly formatted (password-reset -> Password Reset)
		$files = $fs->getFiles();
		$content = $files['/test-project/resources/views/emails/password-reset.php'];
		$this->assertStringContainsString( 'Password Reset', $content );
	}
}
