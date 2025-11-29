<?php

namespace Tests\Scaffolding\Commands\Queue;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\Queue\InstallCommand;

class InstallCommandTest extends TestCase
{
	public function testGetNameReturnsQueueInstall(): void
	{
		$command = new InstallCommand();
		$this->assertEquals( 'queue:install', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$command = new InstallCommand();
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'queue', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new InstallCommand();

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testCamelToSnakeConvertsCorrectly(): void
	{
		$command = new InstallCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'camelToSnake' );
		$method->setAccessible( true );

		$this->assertEquals( 'create_queue_tables', $method->invoke( $command, 'CreateQueueTables' ) );
		$this->assertEquals( 'my_class_name', $method->invoke( $command, 'MyClassName' ) );
		$this->assertEquals( 'simple', $method->invoke( $command, 'Simple' ) );
	}

	public function testGetMigrationTemplateReturnsValidPhp(): void
	{
		$command = new InstallCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'getMigrationTemplate' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'CreateQueueTables' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( '<?php', $result );
		$this->assertStringContainsString( 'class CreateQueueTables', $result );
		$this->assertStringContainsString( 'AbstractMigration', $result );
		$this->assertStringContainsString( 'jobs', $result );
		$this->assertStringContainsString( 'failed_jobs', $result );
	}

	public function testMigrationTemplateHasRequiredTables(): void
	{
		$command = new InstallCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'getMigrationTemplate' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'CreateQueueTables' );

		// Check for jobs table columns
		$this->assertStringContainsString( "'jobs'", $result );
		$this->assertStringContainsString( 'queue', $result );
		$this->assertStringContainsString( 'payload', $result );
		$this->assertStringContainsString( 'attempts', $result );
		$this->assertStringContainsString( 'reserved_at', $result );
		$this->assertStringContainsString( 'available_at', $result );
		$this->assertStringContainsString( 'created_at', $result );

		// Check for failed_jobs table columns
		$this->assertStringContainsString( "'failed_jobs'", $result );
		$this->assertStringContainsString( 'exception', $result );
		$this->assertStringContainsString( 'failed_at', $result );
	}

	public function testIsAlreadyInstalledReturnsBool(): void
	{
		$command = new InstallCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'isAlreadyInstalled' );
		$method->setAccessible( true );

		$result = $method->invoke( $command );

		// Should return a boolean
		$this->assertIsBool( $result );
	}

	public function testExecuteRequiresInteractiveInputAndMockedDependencies(): void
	{
		$this->markTestSkipped( 'Cannot test execute() as it requires interactive input and mocked CLI dependencies' );

		// TODO: Refactor InstallCommand to accept injectable dependencies for testing
		// This would allow testing without actual filesystem operations
	}
}
