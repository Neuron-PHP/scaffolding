<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\EmailCommand;
use Neuron\Core\System\MemoryFileSystem;
use Neuron\Scaffolding\Testing\MemoryTemplateEngine;

class EmailCommandTest extends TestCase
{
	public function testGetNameReturnsMailGenerate(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EmailCommand( $fs, $templates );
		$this->assertEquals( 'mail:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EmailCommand( $fs, $templates );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'email', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new EmailCommand( $fs, $templates );

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testCreateTemplateSuccess(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up template engine with stub content
		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'email.stub', '<h1>{{title}}</h1><div>{{content}}</div>' );

		// Create emails directory
		$fs->addDirectory( '/test-project/resources/views/emails' );

		$command = new EmailCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'createTemplate' );

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

		// Set up template engine with stub content
		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'email.stub', '<h1>{{title}}</h1>' );

		// Don't create emails directory - let command create it

		$command = new EmailCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'createTemplate' );

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

		$templates = new MemoryTemplateEngine();
		$command = new EmailCommand( $fs, $templates );

		// Mock output to prevent errors
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'createTemplate' );

		$result = $method->invoke( $command, 'welcome' );

		$this->assertFalse( $result );
	}

	public function testCreateTemplateFailsWhenStubNotFound(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Don't add template to template engine - let it fail
		$templates = new MemoryTemplateEngine();

		$command = new EmailCommand( $fs, $templates );

		// Mock output to prevent errors
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'createTemplate' );

		$result = $method->invoke( $command, 'welcome' );

		$this->assertFalse( $result );
	}

	public function testCreateTemplateHandlesHyphenatedNames(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up template engine with stub content
		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'email.stub', '<h1>{{title}}</h1>' );

		$fs->addDirectory( '/test-project/resources/views/emails' );

		$command = new EmailCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'createTemplate' );

		$result = $method->invoke( $command, 'password-reset' );

		$this->assertTrue( $result );

		// Verify the title was properly formatted (password-reset -> Password Reset)
		$files = $fs->getFiles();
		$content = $files['/test-project/resources/views/emails/password-reset.php'];
		$this->assertStringContainsString( 'Password Reset', $content );
	}

	public function testCreateTemplateFailsWhenDirectoryCreationFails(): void
	{
		// Create a mock filesystem that fails to create directories
		$fs = $this->createMock( \Neuron\Core\System\IFileSystem::class );
		$fs->method( 'getcwd' )->willReturn( '/test-project' );
		$fs->method( 'isDir' )->willReturn( false );
		$fs->method( 'mkdir' )->willReturn( false );

		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'email.stub', '<h1>{{title}}</h1>' );

		$command = new EmailCommand( $fs, $templates );

		// Mock output to prevent errors
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'createTemplate' );

		$result = $method->invoke( $command, 'welcome' );

		$this->assertFalse( $result );
	}

	public function testCreateTemplateFailsWhenTemplateRenderThrowsException(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$fs->addDirectory( '/test-project/resources/views/emails' );

		// Create a mock template engine that throws exception on render
		$templates = $this->createMock( \Neuron\Scaffolding\Contracts\ITemplateEngine::class );
		$templates->method( 'exists' )->willReturn( true );
		$templates->method( 'render' )->willThrowException( new \Exception( 'Render failed' ) );

		$command = new EmailCommand( $fs, $templates );

		// Mock output to prevent errors
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'createTemplate' );

		$result = $method->invoke( $command, 'welcome' );

		$this->assertFalse( $result );
	}

	public function testCreateTemplateFailsWhenFileWriteFails(): void
	{
		// Create a mock filesystem that fails to write files
		$fs = $this->createMock( \Neuron\Core\System\IFileSystem::class );
		$fs->method( 'getcwd' )->willReturn( '/test-project' );
		$fs->method( 'isDir' )->willReturn( true );
		$fs->method( 'fileExists' )->willReturn( false );
		$fs->method( 'writeFile' )->willReturn( false );

		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'email.stub', '<h1>{{title}}</h1>' );

		$command = new EmailCommand( $fs, $templates );

		// Mock output to prevent errors
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'createTemplate' );

		$result = $method->invoke( $command, 'welcome' );

		$this->assertFalse( $result );
	}
}
