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
 * CLI command for generating a complete Neuron ORM CRUD scaffold.
 *
 * Emits a model, DTO YAML, repository (interface + implementation),
 * attribute-routed controller, field-aware views and (for new tables) a
 * migration. Fields come from a `--fields` spec or from introspecting an
 * existing table via `--from-table`.
 */
class ScaffoldCommand extends Command
{
	private string $_ProjectPath;
	private array $_Messages = [];
	private bool $_HasMvcComponent = false;
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

		$this->_HasMvcComponent = class_exists( '\\Neuron\\Mvc\\Database\\MigrationManager' );
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'scaffold:generate';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Generate a complete Neuron ORM CRUD scaffold (model, DTO, repository, controller, views, migration)';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		$this->addArgument( 'name', true, 'Resource name (e.g., Post or Admin/Post)' );
		$this->addOption( 'fields', 'f', true, 'Field definitions (e.g., "title:string,body:text,published:boolean")' );
		$this->addOption( 'from-table', null, true, 'Introspect an existing table for fields (e.g., jud_docket)' );
		$this->addOption( 'namespace', null, true, 'Controller namespace', 'App\\Controllers' );
		$this->addOption( 'model-namespace', null, true, 'Model namespace', 'App\\Models' );
		$this->addOption( 'repo-namespace', null, true, 'Repository namespace', 'App\\Repositories' );
		$this->addOption( 'api', null, false, 'Generate API controller (JSON responses, no views)' );
		$this->addOption( 'no-migration', null, false, 'Skip migration generation' );
		$this->addOption( 'force', null, false, 'Overwrite existing files' );
	}

	/**
	 * Execute the command
	 */
	public function execute( array $Parameters = [] ): int
	{
		$this->output->info( "\n╔═══════════════════════════════════════╗" );
		$this->output->info( "║  Neuron ORM CRUD Scaffold Generator   ║" );
		$this->output->info( "╚═══════════════════════════════════════╝\n" );

		$resourceName = $this->input->getArgument( 'name' );
		if( empty( $resourceName ) )
		{
			$this->output->error( 'Resource name is required' );
			return 1;
		}

		$info = $this->buildInfo( $resourceName );
		$force = $this->input->hasOption( 'force' );

		// Resolve the field set (from an existing table, or a --fields spec).
		try
		{
			$fields = $this->resolveFieldSet( $info );
		}
		catch( \Exception $e )
		{
			$this->output->error( 'Could not resolve fields: ' . $e->getMessage() );
			return 1;
		}

		$info = $this->applyFieldConventions( $info, $fields );

		$fromTable = $this->input->hasOption( 'from-table' );

		try
		{
			// Migration is only meaningful for new tables.
			if( !$fromTable && !$this->input->hasOption( 'no-migration' ) )
			{
				if( $this->_HasMvcComponent )
				{
					if( !$this->generateMigration( $info ) )
					{
						return 1;
					}
				}
				else
				{
					$this->output->warning( 'MVC component not installed - skipping migration generation' );
					$this->output->info( '   Install via: composer require neuron-php/mvc' );
				}
			}
			elseif( $fromTable )
			{
				$this->output->info( "Using existing table '{$info['tableName']}' - migration skipped." );
			}

			$this->_Messages[] = 'Created model: ' . $this->scaffolder->generateModel( $info, $fields, $force );
			$this->_Messages[] = 'Created DTO: ' . $this->scaffolder->generateDto( $info, $fields, $force );

			foreach( $this->scaffolder->generateRepository( $info, $force ) as $file )
			{
				$this->_Messages[] = 'Created repository: ' . $file;
			}

			$this->_Messages[] = 'Created controller: ' . $this->scaffolder->generateController( $info, $force );

			if( !$info['isApi'] )
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

		$this->printSummary( $info );

		return 0;
	}

	/**
	 * Build the placeholder/info array for the resource.
	 */
	private function buildInfo( string $resourceName ): array
	{
		$info = $this->parseResourceName( $resourceName );

		$info['namespace']      = $this->input->getOption( 'namespace', 'App\\Controllers' );
		$info['modelNamespace'] = $this->input->getOption( 'model-namespace', 'App\\Models' );
		$info['repoNamespace']  = $this->input->getOption( 'repo-namespace', 'App\\Repositories' );
		$info['isApi']          = $this->input->hasOption( 'api' );
		$info['fields']         = $this->input->getOption( 'fields', '' );

		$info['repoInterface'] = 'I' . $info['model'] . 'Repository';
		$info['repoClass']     = 'Database' . $info['model'] . 'Repository';

		// View directory + route name prefix derived from the controller path.
		$info['viewPath']  = strtolower( str_replace( '\\', '/', $info['controllerPath'] ) );
		$info['routeName'] = str_replace( '/', '_', $info['viewPath'] );

		// An explicit --from-table overrides the pluralized convention.
		$fromTable = $this->input->getOption( 'from-table' );
		if( !empty( $fromTable ) && is_string( $fromTable ) )
		{
			$info['tableName'] = $fromTable;
		}

		// Sensible defaults; refined once the field set is known.
		$info['primaryKey']       = 'id';
		$info['primaryKeyGetter'] = 'getId';

		return $info;
	}

	/**
	 * Refine info with details that depend on the resolved field set.
	 */
	private function applyFieldConventions( array $info, FieldSet $fields ): array
	{
		$primary = $fields->primary();

		if( $primary )
		{
			$info['primaryKey']       = $primary->name;
			$info['primaryKeyGetter'] = $primary->getter();
		}

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

		$fieldString = (string)$this->input->getOption( 'fields', '' );
		$fields = FieldSet::fromDefinition( $fieldString );

		return $this->decorateWithConventions( $fields );
	}

	/**
	 * Prepend an auto-increment primary key and append timestamp columns to a
	 * --fields derived set, mirroring the generated migration.
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
		$configPath = $this->findConfigPath();

		if( !$configPath )
		{
			throw new \Exception( 'Config directory not found; cannot introspect database.' );
		}

		$source = $this->loadSettings( $configPath );

		if( !$source )
		{
			throw new \Exception( 'Could not load settings (config/neuron.yaml).' );
		}

		$settings = new SettingManager( $source );
		$config = $settings->getSection( 'database' );

		if( !$config )
		{
			throw new \Exception( 'No "database" section found in settings.' );
		}

		return $config;
	}

	/**
	 * Print the success summary, next steps and DI binding guidance.
	 */
	private function printSummary( array $info ): void
	{
		$this->output->newLine();
		$this->output->success( 'Scaffold generated successfully!' );
		$this->output->newLine();

		foreach( $this->_Messages as $message )
		{
			$this->output->info( "  ✓ " . $message );
		}

		$this->output->newLine();
		$this->output->info( 'Routing: controller actions are mapped via PHP route attributes (#[Get], #[Post], ...).' );
		$this->output->info( '         No routes.yaml entry is required.' );

		$this->output->newLine();
		$this->output->info( 'Bind the repository in your service provider (or rely on the controller fallback):' );
		$this->output->info( "  \$container->bind( \\{$info['repoNamespace']}\\{$info['repoInterface']}::class," );
		$this->output->info( "                    \\{$info['repoNamespace']}\\{$info['repoClass']}::class );" );

		$this->output->newLine();
		$this->output->info( 'Next steps:' );
		if( !$this->input->hasOption( 'from-table' ) && !$this->input->hasOption( 'no-migration' ) && $this->_HasMvcComponent )
		{
			$this->output->info( '  1. Run migration: ./vendor/bin/neuron db:migrate:run' );
			$this->output->info( '  2. Start dev server: php -S localhost:8000 -t public' );
			$this->output->info( "  3. Visit: http://localhost:8000{$info['routePrefix']}" );
		}
		else
		{
			$this->output->info( '  1. Start dev server: php -S localhost:8000 -t public' );
			$this->output->info( "  2. Visit: http://localhost:8000{$info['routePrefix']}" );
		}
		$this->output->newLine();
	}

	/**
	 * Parse resource name into naming components.
	 */
	private function parseResourceName( string $name ): array
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
	 * Generate migration file
	 */
	private function generateMigration( array $info ): bool
	{
		$fields = $this->input->getOption( 'fields', '' );
		$migrationName = 'Create' . $info['models'] . 'Table';

		$configPath = $this->findConfigPath();
		if( !$configPath )
		{
			$this->output->warning( 'Config directory not found - skipping migration' );
			return true;
		}

		try
		{
			$basePath = dirname( $configPath );
			$settings = $this->loadSettings( $configPath );
			$manager = new \Neuron\Mvc\Database\MigrationManager( $basePath, $settings );

			if( !$manager->ensureMigrationsDirectory() )
			{
				$this->output->error( 'Failed to create migrations directory' );
				return false;
			}

			$template = $this->generateMigrationTemplate( $info, $fields );
			$templatePath = $this->_ProjectPath . '/.scaffold_migration_temp.php';

			$this->fs->writeFile( $templatePath, $template );

			list( $exitCode, $output ) = $manager->execute( 'create', [
				'--environment' => $manager->getEnvironment(),
				'--template' => $templatePath,
				'name' => $migrationName
			] );

			@unlink( $templatePath );

			if( $exitCode === 0 )
			{
				$migrationPath = $manager->getMigrationsPath();
				$this->_Messages[] = "Created migration: {$migrationPath}/{$migrationName}";
			}
			else
			{
				$this->output->error( 'Failed to create migration' );
				$this->output->write( $output );
				return false;
			}
		}
		catch( \Exception $e )
		{
			$this->output->error( 'Error creating migration: ' . $e->getMessage() );
			return false;
		}

		return true;
	}

	/**
	 * Generate migration template content
	 */
	private function generateMigrationTemplate( array $info, string $fieldsString ): string
	{
		$fields = $this->parseFields( $fieldsString );
		$tableName = $info['tableName'];

		$fieldDefinitions = [];
		foreach( $fields as $field )
		{
			$line = "\t\t\$table->addColumn('{$field['name']}', '{$field['type']}'";

			if( !empty( $field['options'] ) )
			{
				$optionsStr = [];
				foreach( $field['options'] as $key => $value )
				{
					if( is_bool( $value ) )
					{
						$optionsStr[] = "'{$key}' => " . ($value ? 'true' : 'false');
					}
					elseif( is_int( $value ) )
					{
						$optionsStr[] = "'{$key}' => {$value}";
					}
					else
					{
						$optionsStr[] = "'{$key}' => '{$value}'";
					}
				}
				$line .= ", [" . implode( ', ', $optionsStr ) . "]";
			}

			$line .= ");";
			$fieldDefinitions[] = $line;
		}

		$fieldsCode = empty( $fieldDefinitions )
			? "\t\t// Add your columns here"
			: implode( "\n", $fieldDefinitions );

		return <<<PHP
<?php

use Phinx\Migration\AbstractMigration;

/**
 * Migration for {$tableName} table
 * Generated by Neuron scaffold command
 */
class \$CLASSNAME extends AbstractMigration
{
	/**
	 * Create {$tableName} table
	 */
	public function change(): void
	{
		\$table = \$this->table('{$tableName}');
{$fieldsCode}
		\$table->addTimestamps()
			  ->create();
	}
}

PHP;
	}

	/**
	 * Parse field definitions string
	 */
	private function parseFields( string $fieldsString ): array
	{
		if( empty( $fieldsString ) )
		{
			return [];
		}

		$fields = [];
		$parts = explode( ',', $fieldsString );

		foreach( $parts as $part )
		{
			$part = trim( $part );
			if( empty( $part ) )
			{
				continue;
			}

			if( !str_contains( $part, ':' ) )
			{
				continue;
			}

			list( $name, $type ) = explode( ':', $part, 2 );
			$name = trim( $name );
			$type = trim( $type );

			$phinxType = $this->mapFieldType( $type );
			$options = $this->getFieldOptions( $type, $name );

			$fields[] = [
				'name' => $name,
				'type' => $phinxType,
				'options' => $options
			];
		}

		return $fields;
	}

	/**
	 * Map field type to Phinx type
	 */
	private function mapFieldType( string $type ): string
	{
		return match( strtolower( $type ) ) {
			'string', 'varchar' => 'string',
			'text' => 'text',
			'integer', 'int' => 'integer',
			'biginteger', 'bigint' => 'biginteger',
			'float' => 'float',
			'decimal' => 'decimal',
			'boolean', 'bool' => 'boolean',
			'date' => 'date',
			'datetime', 'timestamp' => 'datetime',
			'time' => 'time',
			'json' => 'json',
			default => 'string'
		};
	}

	/**
	 * Get field options based on type
	 */
	private function getFieldOptions( string $type, string $name ): array
	{
		$options = [];

		if( in_array( strtolower( $type ), ['string', 'varchar'] ) )
		{
			$options['limit'] = 255;
		}

		if( in_array( strtolower( $type ), ['boolean', 'bool'] ) )
		{
			$options['default'] = false;
		}

		if( strtolower( $type ) === 'text' )
		{
			$options['null'] = true;
		}

		return $options;
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

	/**
	 * Find configuration directory
	 */
	private function findConfigPath(): ?string
	{
		$cwd = $this->fs->getcwd();
		$locations = [
			$cwd . '/config',
			dirname( $cwd ) . '/config',
			dirname( $cwd, 2 ) . '/config',
		];

		foreach( $locations as $location )
		{
			if( $this->fs->isDir( $location ) )
			{
				return $location;
			}
		}

		return null;
	}

	/**
	 * Load settings from config directory
	 */
	private function loadSettings( string $configPath ): ?\Neuron\Data\Settings\Source\Yaml
	{
		$configFile = $configPath . '/neuron.yaml';

		if( !$this->fs->fileExists( $configFile ) )
		{
			return null;
		}

		try
		{
			return new \Neuron\Data\Settings\Source\Yaml( $configFile );
		}
		catch( \Exception $e )
		{
			return null;
		}
	}
}
