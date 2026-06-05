<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\JobCommand;
use Neuron\Core\System\MemoryFileSystem;
use Neuron\Scaffolding\Testing\MemoryTemplateEngine;

class JobCommandTest extends TestCase
{
	public function testGetNameReturnsJobGenerate(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );
		$this->assertEquals( 'job:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'job', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testValidateCronExpressionAcceptsValidExpression(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'validateCronExpression' );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		// Valid cron expressions
		$result = $method->invoke( $command, '0 9 * * *' );
		$this->assertTrue( $result );

		$result = $method->invoke( $command, '*/15 * * * *' );
		$this->assertTrue( $result );
	}

	public function testValidateCronExpressionRejectsInvalidExpression(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'validateCronExpression' );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		// Invalid cron expression
		$result = $method->invoke( $command, 'invalid cron' );
		$this->assertFalse( $result );
	}

	public function testGenerateJobSuccess(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up template engine with stub content
		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'job.stub', '<?php namespace {{namespace}}; class {{class}} { protected string $name = "{{jobName}}"; public function handle() {} }' );

		$command = new JobCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateJob' );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$result = $method->invoke( $command, 'SendEmailReminders', 'App\\Jobs' );

		$this->assertTrue( $result );

		// Verify file was created
		$files = $fs->getFiles();
		$this->assertArrayHasKey( '/test-project/app/Jobs/SendEmailReminders.php', $files );

		// Verify content has placeholders replaced
		$content = $files['/test-project/app/Jobs/SendEmailReminders.php'];
		$this->assertStringContainsString( 'namespace App\\Jobs', $content );
		$this->assertStringContainsString( 'class SendEmailReminders', $content );
		$this->assertStringContainsString( 'send_email_reminders', $content );
	}

	public function testGenerateJobCreatesDirectoryIfMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up template engine with stub content
		$templates = new MemoryTemplateEngine();
		$templates->addTemplate( 'job.stub', '<?php namespace {{namespace}}; class {{class}} {}' );

		$command = new JobCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateJob' );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$result = $method->invoke( $command, 'TestJob', 'App\\Jobs' );

		$this->assertTrue( $result );

		// Verify directory was created
		$directories = $fs->getDirectories();
		$this->assertArrayHasKey( '/test-project/app/Jobs', $directories );
	}

	public function testGenerateJobFailsWhenFileExists(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Set up existing file
		$fs->addDirectory( '/test-project/app/Jobs' );
		$fs->addFile( '/test-project/app/Jobs/TestJob.php', 'existing content' );

		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );

		// Mock output and input
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$method = $reflection->getMethod( 'generateJob' );

		$result = $method->invoke( $command, 'TestJob', 'App\\Jobs' );

		$this->assertFalse( $result );
	}

	public function testGenerateJobFailsWhenStubNotFound(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Don't set up stub file - let it fail

		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$reflection = new \ReflectionClass( $command );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$method = $reflection->getMethod( 'generateJob' );

		$result = $method->invoke( $command, 'TestJob', 'App\\Jobs' );

		$this->assertFalse( $result );
	}

	public function testAddToScheduleCreatesNewScheduleEntry(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Create config directory
		$fs->addDirectory( '/test-project/config' );

		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'addToSchedule' );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$result = $method->invoke( $command, 'SendEmailReminders', 'App\\Jobs', '0 9 * * *' );

		$this->assertTrue( $result );

		// Verify file was created
		$files = $fs->getFiles();
		$this->assertArrayHasKey( '/test-project/config/schedule.yaml', $files );

		// Verify YAML content
		$content = $files['/test-project/config/schedule.yaml'];
		$this->assertStringContainsString( 'sendEmailReminders', $content );
		$this->assertStringContainsString( 'App\\Jobs\\SendEmailReminders', $content );
		$this->assertStringContainsString( '0 9 * * *', $content );
	}

	public function testAddToScheduleFailsWhenJobAlreadyScheduledWithoutForce(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Create existing schedule.yaml with job already scheduled
		$fs->addDirectory( '/test-project/config' );
		$existingYaml = "schedule:\n    sendEmailReminders:\n        class: App\\Jobs\\SendEmailReminders\n        cron: '0 9 * * *'\n        args: []\n";
		$fs->addFile( '/test-project/config/schedule.yaml', $existingYaml );

		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'addToSchedule' );

		// Mock output and input (without force)
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( false );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$result = $method->invoke( $command, 'SendEmailReminders', 'App\\Jobs', '0 10 * * *' );

		$this->assertFalse( $result );
	}

	public function testAddToScheduleUpdatesExistingWithForce(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Create existing schedule.yaml with job already scheduled
		$fs->addDirectory( '/test-project/config' );
		$existingYaml = "schedule:\n    sendEmailReminders:\n        class: App\\Jobs\\SendEmailReminders\n        cron: '0 9 * * *'\n        args: []\n";
		$fs->addFile( '/test-project/config/schedule.yaml', $existingYaml );

		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'addToSchedule' );

		// Mock output and input (with force)
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$input = $this->createMock( \Neuron\Cli\Console\Input::class );
		$input->method( 'hasOption' )->willReturn( true );

		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setValue( $command, $output );

		$inputProperty = $reflection->getProperty( 'input' );
		$inputProperty->setValue( $command, $input );

		$result = $method->invoke( $command, 'SendEmailReminders', 'App\\Jobs', '0 10 * * *' );

		$this->assertTrue( $result );

		// Verify the cron expression was updated
		$content = $fs->readFile( '/test-project/config/schedule.yaml' );
		$this->assertStringContainsString( '0 10 * * *', $content );
	}

	public function testToSnakeCaseConvertsCorrectly(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$templates = new MemoryTemplateEngine();
		$command = new JobCommand( $fs, $templates );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'toSnakeCase' );

		$this->assertEquals( 'send_email_reminders', $method->invoke( $command, 'SendEmailReminders' ) );
		$this->assertEquals( 'process_orders', $method->invoke( $command, 'ProcessOrders' ) );
		$this->assertEquals( 'test', $method->invoke( $command, 'Test' ) );
	}


}
