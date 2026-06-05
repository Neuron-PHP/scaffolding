<?php

namespace Neuron\Scaffolding\Commands;

use Neuron\Cli\Commands\Command;
use Neuron\Core\System\IFileSystem;
use Neuron\Core\System\RealFileSystem;
use Neuron\Data\Settings\SettingManager;
use Neuron\Scaffolding\Contracts\ITemplateEngine;
use Neuron\Scaffolding\Schema\Connection;
use Neuron\Scaffolding\Schema\Field;
use Neuron\Scaffolding\Schema\FieldSet;
use Neuron\Scaffolding\Schema\SchemaIntrospector;
use Neuron\Scaffolding\Services\FileTemplateEngine;
use Neuron\Scaffolding\Services\ResourceScaffolder;

/**
 * CLI command for generating an attribute-routed MVC controller and its
 * field-aware views.
 *
 * Routing is provided by PHP route attributes on the generated controller, so
 * no routes.yaml entry is required.
 */
class ControllerCommand extends Command
{
	private string $_ProjectPath;
	private array $_Messages = [];
	private IFileSystem $fs;
	private ITemplateEngine $templates;
	private ResourceScaffolder $scaffolder;

	public function __construct( ?IFileSystem $fs = null, ?ITemplateEngine $templates = null )
	{
		$this->fs = $fs ?? new RealFileSystem();
		$this->_ProjectPath = $this->fs->getcwd();

		if( $templates === null )
		{
			$templates = new FileTemplateEngine( $this->fs, __DIR__ . '/../Stubs' );
		}
		$this->templates = $templates;

		$this->scaffolder = new ResourceScaffolder( $this->fs, $this->templates, $this->_ProjectPath );
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'controller:generate';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Generate an attribute-routed MVC controller with field-aware views';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		$this->addArgument( 'name', true, 'Controller name (e.g., Post or Admin/Post)' );
		$this->addOption( 'namespace', null, true, 'Controller namespace', 'App\\Controllers' );
		$this->addOption( 'model-namespace', null, true, 'Model namespace', 'App\\Models' );
		$this->addOption( 'repo-namespace', null, true, 'Repository namespace', 'App\\Repositories' );
		$this->addOption( 'fields', 'f', true, 'Field definitions (e.g., "title:string,body:text")' );
		$this->addOption( 'from-table', null, true, 'Introspect an existing table for fields' );
		$this->addOption( 'api', null, false, 'Generate API controller (JSON responses)' );
		$this->addOption( 'no-views', null, false, 'Skip view generation' );
		$this->addOption( 'force', null, false, 'Overwrite existing files' );
	}

	/**
	 * Execute the command
	 */
	public function execute( array $Parameters = [] ): int
	{
		$this->output->info( "\n╔═══════════════════════════════════════╗" );
		$this->output->info( "║  MVC Controller Generator             ║" );
		$this->output->info( "╚═══════════════════════════════════════╝\n" );

		$controllerName = $this->input->getArgument( 'name' );
		if( empty( $controllerName ) )
		{
			$this->output->error( 'Controller name is required' );
			return 1;
		}

		$info = $this->buildInfo( $controllerName );
		$force = $this->input->hasOption( 'force' );

		try
		{
			$fields = $this->resolveFieldSet( $info );
		}
		catch( \Exception $e )
		{
			$this->output->error( 'Could not resolve fields: ' . $e->getMessage() );
			return 1;
		}

		$primary = $fields->primary();
		if( $primary )
		{
			$info['primaryKey']       = $primary->name;
			$info['primaryKeyGetter'] = $primary->getter();
		}

		try
		{
			$this->_Messages[] = 'Created controller: ' . $this->scaffolder->generateController( $info, $force );

			if( !$this->input->hasOption( 'no-views' ) && !$info['isApi'] )
			{
				foreach( $this->scaffolder->generateViews( $info, $fields, $force ) as $file )
				{
					$this->_Messages[] = 'Created view: ' . $file;
				}
			}
		}
		catch( \RuntimeException $e )
		{
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->output->newLine();
		$this->output->success( 'Controller generated successfully!' );
		foreach( $this->_Messages as $message )
		{
			$this->output->info( "  " . $message );
		}
		$this->output->newLine();
		$this->output->info( 'Routing: actions are mapped via PHP route attributes (#[Get], #[Post], ...).' );
		$this->output->newLine();

		return 0;
	}

	/**
	 * Build the placeholder/info array for the controller.
	 */
	private function buildInfo( string $controllerName ): array
	{
		$info = $this->parseControllerName( $controllerName );

		$info['namespace']      = $this->input->getOption( 'namespace', 'App\\Controllers' );
		$info['modelNamespace'] = $this->input->getOption( 'model-namespace', 'App\\Models' );
		$info['repoNamespace']  = $this->input->getOption( 'repo-namespace', 'App\\Repositories' );
		$info['isApi']          = $this->input->hasOption( 'api' );

		$info['repoInterface'] = 'I' . $info['model'] . 'Repository';
		$info['repoClass']     = 'Database' . $info['model'] . 'Repository';

		$info['viewPath']  = strtolower( str_replace( '\\', '/', $info['controllerPath'] ) );
		$info['routeName'] = str_replace( '/', '_', $info['viewPath'] );

		$fromTable = $this->input->getOption( 'from-table' );
		if( !empty( $fromTable ) && is_string( $fromTable ) )
		{
			$info['tableName'] = $fromTable;
		}

		$info['primaryKey']       = 'id';
		$info['primaryKeyGetter'] = 'getId';

		return $info;
	}

	/**
	 * Resolve a FieldSet from --from-table introspection or --fields.
	 *
	 * @throws \Exception
	 */
	private function resolveFieldSet( array $info ): FieldSet
	{
		$fromTable = $this->input->getOption( 'from-table' );

		if( !empty( $fromTable ) && is_string( $fromTable ) )
		{
			$pdo = Connection::fromConfig( $this->databaseConfig() );

			return new SchemaIntrospector( $pdo )->introspect( $fromTable );
		}

		$fields = FieldSet::fromDefinition( (string)$this->input->getOption( 'fields', '' ) );

		return $this->decorateWithConventions( $fields );
	}

	/**
	 * Prepend an auto-increment primary key and append timestamp columns.
	 */
	private function decorateWithConventions( FieldSet $fields ): FieldSet
	{
		$all = $fields->all();
		$names = array_map( fn( Field $f ) => $f->name, $all );

		$prefix = [];
		if( !$fields->primary() )
		{
			$prefix[] = new Field( name: 'id', type: 'integer', nullable: false, isPrimary: true, autoIncrement: true );
		}

		$suffix = [];
		foreach( [ 'created_at', 'updated_at' ] as $ts )
		{
			if( !in_array( $ts, $names, true ) )
			{
				$suffix[] = new Field( name: $ts, type: 'datetime', nullable: true );
			}
		}

		return new FieldSet( array_merge( $prefix, $all, $suffix ) );
	}

	/**
	 * Read the database configuration section from the project settings.
	 *
	 * @throws \Exception
	 */
	private function databaseConfig(): array
	{
		$configFile = $this->_ProjectPath . '/config/neuron.yaml';

		if( !$this->fs->fileExists( $configFile ) )
		{
			throw new \Exception( 'config/neuron.yaml not found; cannot introspect database.' );
		}

		$settings = new SettingManager( new \Neuron\Data\Settings\Source\Yaml( $configFile ) );
		$config = $settings->getSection( 'database' );

		if( !$config )
		{
			throw new \Exception( 'No "database" section found in settings.' );
		}

		return $config;
	}

	/**
	 * Parse controller name into naming components.
	 */
	private function parseControllerName( string $name ): array
	{
		$name = preg_replace( '/Controller$/', '', $name );

		$parts = preg_split( '#[/\\\\]+#', trim( $name, '/\\' ) );

		$class = array_pop( $parts );
		$subNamespace = implode( '\\', $parts );

		$model = $class;
		$models = $this->pluralize( $model );
		$variable = lcfirst( $model );
		$variables = lcfirst( $models );
		$tableName = strtolower( $this->underscore( $models ) );

		$controllerPath = empty( $subNamespace ) ? $models : $subNamespace . '/' . $models;
		$routePrefix = '/' . strtolower( str_replace( '\\', '/', $controllerPath ) );

		return [
			'class'          => $class . 'Controller',
			'subNamespace'   => $subNamespace,
			'model'          => $model,
			'models'         => $models,
			'variable'       => $variable,
			'variables'      => $variables,
			'tableName'      => $tableName,
			'controllerPath' => $controllerPath,
			'routePrefix'    => $routePrefix,
		];
	}

	/**
	 * Simple pluralization
	 */
	private function pluralize( string $word ): string
	{
		if( preg_match( '/(s|x|z|ch|sh)$/i', $word ) )
		{
			return $word . 'es';
		}
		elseif( preg_match( '/y$/i', $word ) )
		{
			return preg_replace( '/y$/i', 'ies', $word );
		}
		else
		{
			return $word . 's';
		}
	}

	/**
	 * Convert PascalCase to snake_case
	 */
	private function underscore( string $word ): string
	{
		return strtolower( preg_replace( '/([a-z])([A-Z])/', '$1_$2', $word ) );
	}
}
