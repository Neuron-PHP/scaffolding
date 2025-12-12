<?php

namespace Tests\Scaffolding\Commands\Queue;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\Queue\InstallCommand;
use Neuron\Core\System\MemoryFileSystem;

class InstallCommandTest extends TestCase
{
	public function testGetNameReturnsQueueInstall(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );
		$this->assertEquals( 'queue:install', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'queue', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testCamelToSnakeConvertsCorrectly(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

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
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

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
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

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

	public function testIsAlreadyInstalledReturnsFalseWhenNotInstalled(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'isAlreadyInstalled' );
		$method->setAccessible( true );

		$result = $method->invoke( $command );

		// Should return false when nothing is installed
		$this->assertFalse( $result );
	}

	public function testGenerateMigrationCreatesNewMigration(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateMigration' );
		$method->setAccessible( true );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$result = $method->invoke( $command );

		$this->assertTrue( $result );

		// Verify migration file was created
		$files = $fs->getFiles();
		$migrationFiles = array_filter( array_keys( $files ), function( $path ) {
			return str_contains( $path, '/db/migrate/' ) && str_contains( $path, 'create_queue_tables.php' );
		} );

		$this->assertNotEmpty( $migrationFiles, 'Migration file should be created' );

		// Verify migration content
		$migrationFile = reset( $migrationFiles );
		$content = $files[$migrationFile];
		$this->assertStringContainsString( 'class CreateQueueTables', $content );
		$this->assertStringContainsString( 'AbstractMigration', $content );
		$this->assertStringContainsString( 'jobs', $content );
		$this->assertStringContainsString( 'failed_jobs', $content );
	}

	public function testGenerateMigrationCreatesDirectoryIfMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateMigration' );
		$method->setAccessible( true );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$result = $method->invoke( $command );

		$this->assertTrue( $result );

		// Verify directory was created
		$directories = $fs->getDirectories();
		$this->assertArrayHasKey( '/test-project/db/migrate', $directories );
	}

	public function testAddQueueConfigReturnsFalseWhenNeuronYamlMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'addQueueConfig' );
		$method->setAccessible( true );

		$result = $method->invoke( $command );

		// Should return false when neuron.yaml doesn't exist
		$this->assertFalse( $result );
	}

	public function testGenerateMigrationReturnsTrueWhenMigrationAlreadyExists(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Create migration directory and existing migration
		$fs->addDirectory( '/test-project/db/migrate' );
		$fs->addFile( '/test-project/db/migrate/20250101000000_create_queue_tables.php', '<?php' );

		$command = new InstallCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateMigration' );
		$method->setAccessible( true );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$result = $method->invoke( $command );

		// Should return true even though migration exists
		$this->assertTrue( $result );
	}

	public function testIsAlreadyInstalledReturnsFalseWhenMigrationExistsButConfigMissing(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		// Create migration but not config
		$fs->addDirectory( '/test-project/db/migrate' );
		$fs->addFile( '/test-project/db/migrate/20250101000000_create_queue_tables.php', '<?php' );

		$command = new InstallCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'isAlreadyInstalled' );
		$method->setAccessible( true );

		$result = $method->invoke( $command );

		// Should return false because config is missing
		$this->assertFalse( $result );
	}

	public function testMigrationFileHasCorrectStructure(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'getMigrationTemplate' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'TestClassName' );

		// Verify it's valid PHP
		$this->assertStringStartsWith( '<?php', $result );

		// Verify class structure
		$this->assertStringContainsString( 'class TestClassName extends AbstractMigration', $result );
		$this->assertStringContainsString( 'public function change()', $result );

		// Verify both tables are created
		$this->assertMatchesRegularExpression( '/\$this->table\(\s*[\'"]jobs[\'"]\s*,/', $result );
		$this->assertMatchesRegularExpression( '/\$this->table\(\s*[\'"]failed_jobs[\'"]\s*,/', $result );
	}

	public function testMigrationTemplateHasProperIndexes(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'getMigrationTemplate' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'CreateQueueTables' );

		// Verify jobs table indexes
		$this->assertStringContainsString( "->addIndex( [ 'queue' ] )", $result );
		$this->assertStringContainsString( "->addIndex( [ 'available_at' ] )", $result );
		$this->assertStringContainsString( "->addIndex( [ 'reserved_at' ] )", $result );

		// Verify failed_jobs table indexes
		$this->assertStringContainsString( "->addIndex( [ 'failed_at' ] )", $result );
	}

	public function testGenerateMigrationUsesCorrectTimestampFormat(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'generateMigration' );
		$method->setAccessible( true );

		// Mock output
		$output = $this->createMock( \Neuron\Cli\Console\Output::class );
		$outputProperty = $reflection->getProperty( 'output' );
		$outputProperty->setAccessible( true );
		$outputProperty->setValue( $command, $output );

		$result = $method->invoke( $command );

		$this->assertTrue( $result );

		// Get created file
		$files = $fs->getFiles();
		$migrationFiles = array_filter( array_keys( $files ), function( $path ) {
			return str_contains( $path, '/db/migrate/' ) && str_contains( $path, 'create_queue_tables.php' );
		} );

		$this->assertNotEmpty( $migrationFiles );

		// Verify filename format: YYYYMMDDHHMMSS_create_queue_tables.php
		$filename = basename( reset( $migrationFiles ) );
		$this->assertMatchesRegularExpression( '/^\d{14}_create_queue_tables\.php$/', $filename );
	}

	public function testCamelToSnakeHandlesEdgeCases(): void
	{
		$fs = new MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new InstallCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'camelToSnake' );
		$method->setAccessible( true );

		// Test various cases
		$this->assertEquals( 'a_b_c', $method->invoke( $command, 'ABC' ) );
		$this->assertEquals( 'test', $method->invoke( $command, 'Test' ) );
		$this->assertEquals( 'my_long_class_name', $method->invoke( $command, 'MyLongClassName' ) );
		$this->assertEquals( 'h_t_t_p_request', $method->invoke( $command, 'HTTPRequest' ) );
	}

	public function testAddQueueConfigAppendsQueueConfiguration(): void
	{
		$this->markTestSkipped( 'Cannot test addQueueConfig() with MemoryFileSystem as it depends on Yaml and SettingManager libraries that require real file paths' );
	}

	public function testAddQueueConfigReturnsTrueWhenAlreadyConfigured(): void
	{
		$this->markTestSkipped( 'Cannot test addQueueConfig() with MemoryFileSystem as it depends on Yaml and SettingManager libraries that require real file paths' );
	}
}
