<?php

namespace Tests\Scaffolding\Commands\Docker;

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Core\System\MemoryFileSystem;
use Neuron\Scaffolding\Commands\Docker\InstallCommand;
use PHPUnit\Framework\TestCase;

/**
 * Runs the command against the shipped stubs with an in-memory project.
 */
class InstallCommandTest extends TestCase
{
	private MemoryFileSystem $fs;

	protected function setUp(): void
	{
		parent::setUp();

		$this->fs = new MemoryFileSystem();
		$this->fs->setCwd( '/srv/my-site' );
		$this->fs->mkdir( '/srv/my-site', 0755, true );
	}

	private function install( array $argv = [] ): InstallCommand
	{
		$command = new InstallCommand( $this->fs, $this->templatesFromDisk() );
		$command->configure();

		$input = new Input( $argv );
		$input->parse( $command );

		$command->setInput( $input );
		$command->setOutput( $this->createMock( Output::class ) );

		$this->assertEquals( 0, $command->execute(), 'command should succeed' );

		return $command;
	}

	/**
	 * The real stubs, read with the real filesystem.
	 */
	private function templatesFromDisk(): \Neuron\Scaffolding\Services\FileTemplateEngine
	{
		return new \Neuron\Scaffolding\Services\FileTemplateEngine(
			new \Neuron\Core\System\RealFileSystem(),
			dirname( __DIR__, 4 ) . '/src/Scaffolding/Stubs/docker'
		);
	}

	private function file( string $relative ): string
	{
		$content = $this->fs->readFile( '/srv/my-site/' . $relative );
		$this->assertIsString( $content, "{$relative} should exist" );

		return $content;
	}

	public function testMetadata(): void
	{
		$command = new InstallCommand( $this->fs, $this->templatesFromDisk() );

		$this->assertEquals( 'docker:install', $command->getName() );
		$this->assertStringContainsString( 'Dockerfile', $command->getDescription() );
	}

	public function testDefaultsWriteSqliteStackWithAllThreeRoles(): void
	{
		$command = $this->install();

		$this->assertEquals( [
			'Dockerfile' => 'created',
			'.dockerignore' => 'created',
			'docker-compose.yml' => 'created',
			'.docker/php.ini' => 'created',
			'.docker/vhost.conf' => 'created',
			'.docker/entrypoint.sh' => 'created',
		], $command->getResults() );

		$dockerfile = $this->file( 'Dockerfile' );
		$this->assertStringContainsString( 'ARG PHP_VERSION=' . InstallCommand::DEFAULT_PHP, $dockerfile );
		$this->assertStringContainsString( 'FROM php:${PHP_VERSION}-apache AS base', $dockerfile );
		$this->assertStringContainsString( 'pcntl', $dockerfile, 'pcntl is required for job timeouts and graceful shutdown' );
		$this->assertStringContainsString( 'pdo_sqlite', $dockerfile );
		$this->assertStringNotContainsString( 'pdo_mysql', $dockerfile );
		$this->assertStringNotContainsString( 'pdo_pgsql', $dockerfile );
		$this->assertStringNotContainsString( ' redis', $dockerfile );
		$this->assertStringContainsString( 'DOCUMENT_ROOT=/var/www/public_html', $dockerfile );
		$this->assertStringContainsString( 'ENTRYPOINT ["neuron-entrypoint"]', $dockerfile );
		$this->assertStringContainsString( 'CMD ["apache2-foreground"]', $dockerfile );
		$this->assertStringContainsString( 'FROM base AS dev', $dockerfile );
		$this->assertStringContainsString( 'FROM base AS prod', $dockerfile );
		$this->assertStringNotContainsString( '{{', $dockerfile );

		$compose = $this->file( 'docker-compose.yml' );
		$this->assertStringContainsString( 'image: my-site', $compose );
		$this->assertStringContainsString( "  scheduler:\n", $compose );
		$this->assertStringContainsString( '"jobs:schedule"', $compose );
		$this->assertStringContainsString( "  worker:\n", $compose );
		$this->assertStringContainsString( '"--queue=default"', $compose );
		$this->assertStringContainsString( 'stop_grace_period: 90s', $compose );
		$this->assertStringContainsString( 'init: true', $compose );
		$this->assertStringNotContainsString( 'depends_on', $compose );
		$this->assertStringNotContainsString( 'DATABASE_URL', $compose, 'sqlite lives in ./storage; no env wiring' );
		$this->assertStringNotContainsString( "\n  db:", $compose );
		$this->assertStringNotContainsString( "\nvolumes:", $compose );
		$this->assertStringContainsString( 'start web + scheduler + worker', $compose );
		$this->assertStringNotContainsString( '{{', $compose );

		$vhost = $this->file( '.docker/vhost.conf' );
		$this->assertStringContainsString( 'DocumentRoot "/var/www/public_html"', $vhost );

		$entrypoint = $this->file( '.docker/entrypoint.sh' );
		$this->assertStringContainsString( 'exec "$@"', $entrypoint, 'exec so PHP becomes PID 1 and receives SIGTERM' );

		$this->assertStringContainsString( 'memory_limit = 256M', $this->file( '.docker/php.ini' ) );
		$this->assertStringContainsString( "vendor\n", $this->file( '.dockerignore' ) );
	}

	public function testGeneratedComposeIsValidYaml(): void
	{
		$this->install( [ '--db=mysql', '--redis' ] );

		$parsed = \Symfony\Component\Yaml\Yaml::parse( $this->file( 'docker-compose.yml' ) );

		$this->assertEquals( [ 'app', 'scheduler', 'worker', 'db', 'redis' ], array_keys( $parsed['services'] ) );
		$this->assertEquals( [ 'db-data', 'redis-data' ], array_keys( $parsed['volumes'] ) );
		$this->assertEquals( 'service_healthy', $parsed['services']['worker']['depends_on']['db']['condition'] );
		$this->assertEquals(
			'mysql://${DB_USER:-neuron}:${DB_PASS:-neuron}@db:3306/${DB_NAME:-neuron}?charset=utf8mb4',
			$parsed['services']['worker']['environment']['DATABASE_URL'],
			'every PHP role is pointed at the db service through the environment'
		);
		$this->assertEquals( $parsed['services']['worker']['environment']['DATABASE_URL'], $parsed['services']['app']['environment']['DATABASE_URL'] );
		$this->assertEquals( 'service_healthy', $parsed['services']['worker']['depends_on']['redis']['condition'] );
		$this->assertEquals( 'www-data', $parsed['services']['worker']['user'] );
		$this->assertEquals( 'SIGTERM', $parsed['services']['worker']['stop_signal'], 'base image STOPSIGNAL is SIGWINCH; CLI roles need SIGTERM' );
		$this->assertEquals( 'SIGTERM', $parsed['services']['scheduler']['stop_signal'] );
		$this->assertArrayNotHasKey( 'stop_signal', $parsed['services']['app'], 'Apache keeps its graceful SIGWINCH' );
		$this->assertEquals( [ 'php', 'vendor/bin/neuron', 'jobs:schedule' ], $parsed['services']['scheduler']['command'] );
		$this->assertEquals( 'dev', $parsed['services']['app']['build']['target'] );
		$this->assertArrayHasKey( 'ports', $parsed['services']['app'] );
		$this->assertArrayNotHasKey( 'ports', $parsed['services']['worker'] );
	}

	public function testMysqlAddsExtensionServiceAndVolume(): void
	{
		$this->install( [ '--db=mysql' ] );

		$this->assertStringContainsString( 'pdo_mysql', $this->file( 'Dockerfile' ) );

		$compose = $this->file( 'docker-compose.yml' );
		$this->assertStringContainsString( 'image: mysql:8.4', $compose );
		$this->assertStringContainsString( "  depends_on:\n    db:\n      condition: service_healthy", $compose );
		$this->assertStringContainsString( "volumes:\n  db-data:\n", $compose );
		$this->assertStringNotContainsString( 'redis', $compose );
	}

	public function testPostgresAliasesAreAccepted(): void
	{
		$this->install( [ '--db=postgresql' ] );

		$this->assertStringContainsString( 'pdo_pgsql', $this->file( 'Dockerfile' ) );
		$this->assertStringContainsString( 'image: postgres:17', $this->file( 'docker-compose.yml' ) );
		$this->assertStringContainsString( 'DATABASE_URL: postgresql://${DB_USER:-neuron}:${DB_PASS:-neuron}@db:5432/${DB_NAME:-neuron}', $this->file( 'docker-compose.yml' ) );
	}

	public function testRedisFlagAddsExtensionAndService(): void
	{
		$this->install( [ '--redis' ] );

		$this->assertStringContainsString( ' redis', $this->file( 'Dockerfile' ) );

		$compose = $this->file( 'docker-compose.yml' );
		$this->assertStringContainsString( 'image: redis:7-alpine', $compose );
		$this->assertStringContainsString( '"--appendonly", "yes"', $compose );
		$this->assertStringContainsString( "    redis:\n      condition: service_healthy", $compose );
		$this->assertStringContainsString( "volumes:\n  redis-data:\n", $compose );
	}

	public function testDefaultsAreDetectedFromConfigAndLayout(): void
	{
		$this->fs->mkdir( '/srv/my-site/public', 0755, true );
		$this->fs->writeFile( '/srv/my-site/config/neuron.yaml', <<<YAML
database:
  url: postgresql://u:p@localhost/app
queue:
  driver: redis
  default: emails
YAML );

		$this->install();

		$dockerfile = $this->file( 'Dockerfile' );
		$this->assertStringContainsString( 'pdo_pgsql', $dockerfile );
		$this->assertStringContainsString( ' redis', $dockerfile );
		$this->assertStringContainsString( 'DOCUMENT_ROOT=/var/www/public', $dockerfile );
		$this->assertStringNotContainsString( 'public_html', $dockerfile );

		$compose = $this->file( 'docker-compose.yml' );
		$this->assertStringContainsString( '"--queue=emails"', $compose );
		$this->assertStringContainsString( 'image: postgres:17', $compose );
		$this->assertStringContainsString( 'image: redis:7-alpine', $compose );
	}

	public function testDatabaseAdapterInConfigIsDetected(): void
	{
		$this->fs->writeFile( '/srv/my-site/config/neuron.yaml', "database:\n  adapter: mysql\n" );

		$this->install();

		$this->assertStringContainsString( 'pdo_mysql', $this->file( 'Dockerfile' ) );
	}

	public function testExplicitOptionsOverrideDetection(): void
	{
		$this->fs->mkdir( '/srv/my-site/public', 0755, true );
		$this->fs->writeFile( '/srv/my-site/config/neuron.yaml', "database:\n  adapter: mysql\nqueue:\n  default: emails\n" );

		$this->install( [ '--db=sqlite', '--docroot=www', '--queue=high,default', '--php=8.4', '--image=acme/site' ] );

		$dockerfile = $this->file( 'Dockerfile' );
		$this->assertStringContainsString( 'ARG PHP_VERSION=8.4', $dockerfile );
		$this->assertStringNotContainsString( 'pdo_mysql', $dockerfile );
		$this->assertStringContainsString( 'DOCUMENT_ROOT=/var/www/www', $dockerfile );

		$compose = $this->file( 'docker-compose.yml' );
		$this->assertStringContainsString( '"--queue=high,default"', $compose );
		$this->assertStringContainsString( 'image: acme/site', $compose );
	}

	public function testWebOnlyOmitsJobServices(): void
	{
		$this->install( [ '--web-only' ] );

		$compose = $this->file( 'docker-compose.yml' );
		$this->assertStringNotContainsString( 'scheduler', $compose );
		$this->assertStringNotContainsString( 'jobs:work', $compose );
		$this->assertStringContainsString( 'start web' . "\n", $compose );

		$parsed = \Symfony\Component\Yaml\Yaml::parse( $compose );
		$this->assertEquals( [ 'app' ], array_keys( $parsed['services'] ) );
	}

	public function testRailwayFlagWritesRailwayJson(): void
	{
		$command = $this->install( [ '--railway' ] );

		$this->assertEquals( 'created', $command->getResults()['railway.json'] );

		$json = json_decode( $this->file( 'railway.json' ), true );
		$this->assertEquals( 'Dockerfile', $json['build']['dockerfilePath'] );
		$this->assertEquals( [ 'php vendor/bin/neuron db:migrate' ], $json['deploy']['preDeployCommand'] );
	}

	public function testExistingFilesAreSkippedUnlessForced(): void
	{
		$this->fs->writeFile( '/srv/my-site/Dockerfile', 'FROM scratch' );
		$this->fs->mkdir( '/srv/my-site/.docker', 0755, true );
		$this->fs->writeFile( '/srv/my-site/.docker/php.ini', 'custom' );

		$command = $this->install();

		$this->assertEquals( 'skipped', $command->getResults()['Dockerfile'] );
		$this->assertEquals( 'skipped', $command->getResults()['.docker/php.ini'] );
		$this->assertEquals( 'created', $command->getResults()['docker-compose.yml'] );
		$this->assertEquals( 'FROM scratch', $this->file( 'Dockerfile' ) );
		$this->assertEquals( 'custom', $this->file( '.docker/php.ini' ) );

		$forced = $this->install( [ '--force' ] );

		$this->assertEquals( 'overwritten', $forced->getResults()['Dockerfile'] );
		$this->assertStringContainsString( 'FROM php:', $this->file( 'Dockerfile' ) );
	}

	public function testInvalidDatabaseFails(): void
	{
		$command = new InstallCommand( $this->fs, $this->templatesFromDisk() );
		$command->configure();

		$input = new Input( [ '--db=oracle' ] );
		$input->parse( $command );

		$output = $this->createMock( Output::class );
		$output->expects( $this->once() )->method( 'error' )->with( $this->stringContains( "Unknown database 'oracle'" ) );

		$command->setInput( $input );
		$command->setOutput( $output );

		$this->assertEquals( 1, $command->execute() );
		$this->assertFalse( $this->fs->fileExists( '/srv/my-site/Dockerfile' ) );
	}

	public function testInvalidPhpVersionFails(): void
	{
		$command = new InstallCommand( $this->fs, $this->templatesFromDisk() );
		$command->configure();

		$input = new Input( [ '--php=latest' ] );
		$input->parse( $command );

		$command->setInput( $input );
		$command->setOutput( $this->createMock( Output::class ) );

		$this->assertEquals( 1, $command->execute() );
	}

	public function testAllStubsRenderWithoutLeftoverPlaceholders(): void
	{
		$command = $this->install( [ '--db=pgsql', '--redis', '--railway' ] );

		foreach( array_keys( $command->getResults() ) as $relative )
		{
			$this->assertStringNotContainsString( '{{', $this->file( $relative ), "{$relative} has an unrendered placeholder" );
		}
	}
}
