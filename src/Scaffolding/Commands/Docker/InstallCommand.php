<?php

namespace Neuron\Scaffolding\Commands\Docker;

use Neuron\Cli\Commands\Command;
use Neuron\Core\System\IFileSystem;
use Neuron\Core\System\RealFileSystem;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Yaml;
use Neuron\Scaffolding\Contracts\ITemplateEngine;
use Neuron\Scaffolding\Services\FileTemplateEngine;

/**
 * Generate Docker files for a Neuron application.
 *
 * Writes a multi-stage Dockerfile (dev / prod targets) that serves the web,
 * scheduler and queue-worker roles from one image, a docker-compose.yml that
 * runs all three plus an optional database and Redis, and the supporting
 * .docker/ files. Defaults are read from config/neuron.yaml where possible
 * (database adapter, queue driver and default queue) and from the project
 * layout (document root). Existing files are left alone unless --force.
 */
class InstallCommand extends Command
{
	public const DEFAULT_PHP = '8.5';

	private const DATABASES = [ 'sqlite', 'mysql', 'pgsql' ];

	private string $_projectPath;
	private IFileSystem $fs;
	private ITemplateEngine $templates;

	/** @var array<string, string> relative path => status (created|skipped|overwritten) */
	private array $_results = [];

	public function __construct( ?IFileSystem $fs = null, ?ITemplateEngine $templates = null )
	{
		$this->fs = $fs ?? new RealFileSystem();
		$this->_projectPath = rtrim( $this->fs->getcwd(), '/' );
		$this->templates = $templates ?? new FileTemplateEngine( $this->fs, __DIR__ . '/../../Stubs/docker' );
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'docker:install';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Generate a Dockerfile and docker-compose.yml that run the web, scheduler and queue-worker roles';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		$this->addOption( 'php', null, true, 'PHP version for the base image', self::DEFAULT_PHP );
		$this->addOption( 'db', null, true, 'Database service: sqlite, mysql or pgsql (default: from config/neuron.yaml, else sqlite)' );
		$this->addOption( 'redis', null, false, 'Add a Redis service and the phpredis extension (default: when queue.driver is redis)' );
		$this->addOption( 'docroot', null, true, 'Document root directory (default: public_html or public, whichever exists)' );
		$this->addOption( 'queue', null, true, 'Queue(s) the worker service processes (default: queue.default from config, else "default")' );
		$this->addOption( 'image', null, true, 'Image name for docker compose (default: project directory name)' );
		$this->addOption( 'web-only', null, false, 'Omit the scheduler and worker services' );
		$this->addOption( 'railway', null, false, 'Also write railway.json pointing at the Dockerfile' );
		$this->addOption( 'force', 'f', false, 'Overwrite existing files' );
	}

	/**
	 * Execute the command
	 */
	public function execute( array $parameters = [] ): int
	{
		$this->output->info( "╔═══════════════════════════════════════╗" );
		$this->output->info( "║  Docker Installation                  ║" );
		$this->output->info( "╚═══════════════════════════════════════╝" );
		$this->output->write( "\n" );

		try
		{
			$options = $this->resolveOptions();
		}
		catch( \InvalidArgumentException $e )
		{
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->output->info( "PHP {$options['php']}, database: {$options['db']}, document root: {$options['docroot']}" . ( $options['redis'] ? ', redis' : '' ) );
		$this->output->write( "\n" );

		$force = $this->input->hasOption( 'force' );

		$files = [
			'Dockerfile' => $this->renderDockerfile( $options ),
			'.dockerignore' => $this->templates->render( 'dockerignore.stub', [] ),
			'docker-compose.yml' => $this->renderCompose( $options ),
			'.docker/php.ini' => $this->templates->render( 'php.ini.stub', [] ),
			'.docker/vhost.conf' => $this->templates->render( 'vhost.conf.stub', [ 'docroot' => $options['docroot'] ] ),
			'.docker/entrypoint.sh' => $this->templates->render( 'entrypoint.sh.stub', [] ),
		];

		if( $options['railway'] )
		{
			$files['railway.json'] = $this->templates->render( 'railway.json.stub', [] );
		}

		foreach( $files as $relative => $content )
		{
			if( !$this->writeFile( $relative, $content, $force ) )
			{
				return 1;
			}
		}

		$this->output->write( "\n" );
		$this->printNextSteps( $options );

		return 0;
	}

	/**
	 * Files written by the last run and what happened to each.
	 *
	 * @return array<string, string>
	 */
	public function getResults(): array
	{
		return $this->_results;
	}

	/**
	 * Merge CLI options with values detected from the project.
	 *
	 * @return array{php: string, db: string, redis: bool, docroot: string, queues: string, image: string, webOnly: bool, railway: bool}
	 * @throws \InvalidArgumentException
	 */
	private function resolveOptions(): array
	{
		$settings = $this->loadSettings();

		$php = (string)( $this->input->getOption( 'php' ) ?: self::DEFAULT_PHP );

		if( !preg_match( '/^\d+\.\d+$/', $php ) )
		{
			throw new \InvalidArgumentException( "Invalid PHP version '{$php}'; expected MAJOR.MINOR, e.g. 8.5" );
		}

		$db = strtolower( (string)( $this->input->getOption( 'db' ) ?: $this->detectDatabase( $settings ) ) );
		$db = match( $db )
		{
			'postgres', 'postgresql' => 'pgsql',
			'mariadb' => 'mysql',
			default => $db
		};

		if( !in_array( $db, self::DATABASES, true ) )
		{
			throw new \InvalidArgumentException( "Unknown database '{$db}'; use one of " . implode( ', ', self::DATABASES ) );
		}

		$docroot = trim( (string)( $this->input->getOption( 'docroot' ) ?: $this->detectDocroot() ), '/' );

		if( $docroot === '' || str_contains( $docroot, '..' ) )
		{
			throw new \InvalidArgumentException( "Invalid document root '{$docroot}'" );
		}

		$redis = $this->input->hasOption( 'redis' )
			|| ( $settings && $settings->get( 'queue', 'driver' ) === 'redis' );

		$queues = (string)( $this->input->getOption( 'queue' ) ?: ( $settings?->get( 'queue', 'default' ) ?: 'default' ) );

		return [
			'php' => $php,
			'db' => $db,
			'redis' => $redis,
			'docroot' => $docroot,
			'queues' => $queues,
			'image' => (string)( $this->input->getOption( 'image' ) ?: $this->defaultImageName() ),
			'webOnly' => $this->input->hasOption( 'web-only' ),
			'railway' => $this->input->hasOption( 'railway' ),
		];
	}

	/**
	 * @return SettingManager|null null when config/neuron.yaml is missing or unreadable
	 */
	private function loadSettings(): ?SettingManager
	{
		$configFile = $this->_projectPath . '/config/neuron.yaml';

		if( !$this->fs->fileExists( $configFile ) )
		{
			return null;
		}

		try
		{
			return new SettingManager( new Yaml( $configFile, $this->fs ) );
		}
		catch( \Throwable $e )
		{
			return null;
		}
	}

	/**
	 * Database adapter from `database.adapter` or the scheme of `database.url`.
	 *
	 * @param SettingManager|null $settings
	 * @return string
	 */
	private function detectDatabase( ?SettingManager $settings ): string
	{
		if( !$settings )
		{
			return 'sqlite';
		}

		$adapter = $settings->get( 'database', 'adapter' );

		if( is_string( $adapter ) && $adapter !== '' )
		{
			return $adapter;
		}

		$url = $settings->get( 'database', 'url' );

		if( is_string( $url ) && preg_match( '/^([a-z]+):/i', $url, $m ) )
		{
			return strtolower( $m[1] );
		}

		return 'sqlite';
	}

	/**
	 * @return string
	 */
	private function detectDocroot(): string
	{
		foreach( [ 'public_html', 'public' ] as $candidate )
		{
			if( $this->fs->isDir( $this->_projectPath . '/' . $candidate ) )
			{
				return $candidate;
			}
		}

		return 'public_html';
	}

	/**
	 * Project directory name made safe for an image tag.
	 *
	 * @return string
	 */
	private function defaultImageName(): string
	{
		$name = strtolower( basename( $this->_projectPath ) );
		$name = preg_replace( '/[^a-z0-9._-]+/', '-', $name ) ?? '';
		$name = trim( $name, '-._' );

		return $name !== '' ? $name : 'neuron-app';
	}

	/**
	 * @param array $options
	 * @return string
	 */
	private function renderDockerfile( array $options ): string
	{
		$extensions = [ 'pdo_sqlite' ];

		if( $options['db'] === 'mysql' )
		{
			$extensions[] = 'pdo_mysql';
		}
		elseif( $options['db'] === 'pgsql' )
		{
			$extensions[] = 'pdo_pgsql';
		}

		if( $options['redis'] )
		{
			$extensions[] = 'redis';
		}

		return $this->templates->render( 'Dockerfile.stub', [
			'php_version' => $options['php'],
			'docroot' => $options['docroot'],
			'php_extensions' => implode( ' ', $extensions ),
		] );
	}

	/**
	 * @param array $options
	 * @return string
	 */
	private function renderCompose( array $options ): string
	{
		$dependencies = [];
		$extra = '';
		$volumes = [];
		$roles = [ 'web' ];

		if( !$options['webOnly'] )
		{
			$roles[] = 'scheduler';
			$roles[] = 'worker';
		}

		if( $options['db'] !== 'sqlite' )
		{
			$extra .= $this->templates->render( "compose.{$options['db']}.stub", [] );
			$dependencies[] = 'db';
			$volumes[] = 'db-data';
			$roles[] = $options['db'];
		}

		if( $options['redis'] )
		{
			$extra .= $this->templates->render( 'compose.redis.stub', [] );
			$dependencies[] = 'redis';
			$volumes[] = 'redis-data';
			$roles[] = 'redis';
		}

		// DATABASE_URL overrides config/neuron.yaml inside the containers, so
		// the stack needs no config edit to reach the db service.
		$databaseUrl = match( $options['db'] )
		{
			'mysql' => "    DATABASE_URL: mysql://\${DB_USER:-neuron}:\${DB_PASS:-neuron}@db:3306/\${DB_NAME:-neuron}?charset=utf8mb4\n",
			'pgsql' => "    DATABASE_URL: postgresql://\${DB_USER:-neuron}:\${DB_PASS:-neuron}@db:5432/\${DB_NAME:-neuron}\n",
			default => ''
		};

		$dependsOn = '';

		if( $dependencies )
		{
			$dependsOn = "  depends_on:\n";

			foreach( $dependencies as $service )
			{
				$dependsOn .= "    {$service}:\n      condition: service_healthy\n";
			}
		}

		$volumeBlock = '';

		if( $volumes )
		{
			$volumeBlock = "\nvolumes:\n" . implode( '', array_map( fn( $v ) => "  {$v}:\n", $volumes ) );
		}

		return $this->templates->render( 'docker-compose.yml.stub', [
			'image' => $options['image'],
			'services_hint' => implode( ' + ', $roles ),
			'database_url' => $databaseUrl,
			'depends_on' => $dependsOn,
			'job_services' => $options['webOnly'] ? '' : $this->templates->render( 'compose.jobs.stub', [ 'queues' => $options['queues'] ] ),
			'extra_services' => $extra,
			'volumes' => $volumeBlock,
		] );
	}

	/**
	 * Write one generated file, honouring --force.
	 *
	 * @param string $relative
	 * @param string $content
	 * @param bool $force
	 * @return bool false on an I/O failure
	 */
	private function writeFile( string $relative, string $content, bool $force ): bool
	{
		$path = $this->_projectPath . '/' . $relative;
		$exists = $this->fs->fileExists( $path );

		if( $exists && !$force )
		{
			$this->_results[ $relative ] = 'skipped';
			$this->output->warning( "Exists, skipped: {$relative} (use --force to overwrite)" );
			return true;
		}

		$dir = dirname( $path );

		if( !$this->fs->isDir( $dir ) && !$this->fs->mkdir( $dir, 0755, true ) )
		{
			$this->output->error( "Failed to create directory: {$dir}" );
			return false;
		}

		if( $this->fs->writeFile( $path, $content ) === false )
		{
			$this->output->error( "Failed to write: {$relative}" );
			return false;
		}

		$this->_results[ $relative ] = $exists ? 'overwritten' : 'created';
		$this->output->success( ( $exists ? 'Overwrote: ' : 'Created: ' ) . $relative );

		return true;
	}

	/**
	 * @param array $options
	 */
	private function printNextSteps( array $options ): void
	{
		$this->output->success( "Docker files installed." );
		$this->output->write( "\n" );

		if( $options['db'] !== 'sqlite' )
		{
			$this->output->info( "The PHP services get DATABASE_URL from docker-compose.yml, which overrides" );
			$this->output->info( "the database section of config/neuron.yaml inside the containers." );
			$this->output->info( "Credentials come from DB_USER / DB_PASS / DB_NAME in .env (default: neuron)." );
			$this->output->write( "\n" );
		}

		if( $options['redis'] )
		{
			$this->output->info( "Point the queue at the redis service in config/neuron.yaml:" );
			$this->output->info( "  queue:" );
			$this->output->info( "    driver: redis" );
			$this->output->info( "    redis:" );
			$this->output->info( "      url: redis://redis:6379/0" );
			$this->output->write( "\n" );
		}

		if( $options['db'] === 'sqlite' && !$options['webOnly'] )
		{
			$this->output->warning( "SQLite is shared by the web, scheduler and worker containers through ./storage." );
			$this->output->info( "That is fine for development; use --db=mysql or --db=pgsql before running the three roles under load." );
			$this->output->write( "\n" );
		}

		$this->output->info( "Start the stack:" );
		$this->output->info( "  composer install" );
		$this->output->info( "  docker compose up -d --build" );
		$this->output->info( "  docker compose exec app php vendor/bin/neuron db:migrate" );
		$this->output->write( "\n" );

		if( !$options['webOnly'] )
		{
			$this->output->info( "Jobs:" );
			$this->output->info( "  docker compose up -d --scale worker=3     # more workers" );
			$this->output->info( "  docker compose exec app php vendor/bin/neuron jobs:stats" );
			$this->output->info( "  docker compose restart worker scheduler   # after pulling new code" );
			$this->output->write( "\n" );
		}

		$this->output->info( "Production image:" );
		$this->output->info( "  docker build --target prod -t {$options['image']}:latest ." );
		$this->output->info( "  Run the same image with the command overridden per role:" );
		$this->output->info( "    web        (default)       apache2-foreground" );

		if( !$options['webOnly'] )
		{
			$this->output->info( "    scheduler  php vendor/bin/neuron jobs:schedule" );
			$this->output->info( "    worker     php vendor/bin/neuron jobs:work --queue={$options['queues']} --memory=128 --max-time=3600" );
			$this->output->info( "  Give the scheduler and worker containers --stop-signal SIGTERM (the base image stops Apache with SIGWINCH)." );
		}

		if( $options['railway'] )
		{
			$this->output->write( "\n" );
			$this->output->info( "Railway: railway.json builds the Dockerfile; set the service's Docker build target to 'prod'." );

			if( !$options['webOnly'] )
			{
				$this->output->info( "Add scheduler and worker services from the same repo with the start commands above and no HTTP health check." );
			}
		}
	}
}
