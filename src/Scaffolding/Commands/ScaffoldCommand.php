<?php

namespace Neuron\Scaffolding\Commands;

use Neuron\Cli\Commands\Command;
use Neuron\Core\System\IFileSystem;
use Neuron\Core\System\RealFileSystem;
use Neuron\Scaffolding\Contracts\ITemplateEngine;
use Neuron\Scaffolding\Services\FileTemplateEngine;
use Symfony\Component\Yaml\Yaml;

/**
 * CLI command for generating complete CRUD scaffolds.
 *
 * Generates controller, views, routes, and optionally migrations.
 * Similar to Rails' scaffold generator for rapid prototyping.
 */
class ScaffoldCommand extends Command
{
	private string $_ProjectPath;
	private array $_Messages = [];
	private bool $_HasMvcComponent = false;
	private IFileSystem $fs;
	private ITemplateEngine $templates;

	public function __construct( ?IFileSystem $fs = null, ?ITemplateEngine $templates = null )
	{
		$this->fs = $fs ?? new RealFileSystem();
		$this->_ProjectPath = $this->fs->getcwd();

		// Default to FileTemplateEngine if not provided
		if( $templates === null )
		{
			$stubPath = __DIR__ . '/../Stubs';
			$templates = new FileTemplateEngine( $this->fs, $stubPath );
		}
		$this->templates = $templates;

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
		return 'Generate complete CRUD scaffold (controller, views, routes, migration)';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		$this->addArgument( 'name', true, 'Resource name (e.g., Post or Admin/Post)' );
		$this->addOption( 'fields', 'f', true, 'Field definitions (e.g., "title:string,body:text,published:boolean")' );
		$this->addOption( 'namespace', null, true, 'Controller namespace', 'App\\Controllers' );
		$this->addOption( 'api', null, false, 'Generate API controller (JSON responses, no views)' );
		$this->addOption( 'no-migration', null, false, 'Skip migration generation' );
		$this->addOption( 'filter', null, true, 'Route filter (e.g., auth)' );
		$this->addOption( 'force', 'f', false, 'Overwrite existing files' );
	}

	/**
	 * Execute the command
	 */
	public function execute( array $Parameters = [] ): int
	{
		$this->output->info( "\n╔═══════════════════════════════════════╗" );
		$this->output->info( "║  Full Stack Scaffold Generator        ║" );
		$this->output->info( "╚═══════════════════════════════════════╝\n" );

		// Get resource name
		$resourceName = $this->input->getArgument( 'name' );
		if( empty( $resourceName ) )
		{
			$this->output->error( 'Resource name is required' );
			return 1;
		}

		// Parse resource info
		$info = $this->parseResourceName( $resourceName );
		$info['namespace'] = $this->input->getOption( 'namespace', 'App\\Controllers' );
		$info['filter'] = $this->input->getOption( 'filter' );
		$info['isApi'] = $this->input->hasOption( 'api' );
		$info['fields'] = $this->input->getOption( 'fields', '' );

		// Generate migration (if enabled and MVC available)
		if( !$this->input->hasOption( 'no-migration' ) )
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

		// Generate controller
		if( !$this->generateController( $info ) )
		{
			return 1;
		}

		// Generate views (unless --no-views or --api)
		if( !$info['isApi'] )
		{
			if( !$this->generateViews( $info ) )
			{
				return 1;
			}
		}

		// Generate routes
		if( !$this->generateRoutes( $info ) )
		{
			return 1;
		}

		// Show summary
		$this->output->newLine();
		$this->output->success( 'Scaffold generated successfully!' );
		$this->output->newLine();
		foreach( $this->_Messages as $message )
		{
			$this->output->info( "  ✓ " . $message );
		}

		// Show next steps
		$this->output->newLine();
		$this->output->info( "Next steps:" );
		if( !$this->input->hasOption( 'no-migration' ) && $this->_HasMvcComponent )
		{
			$this->output->info( "  1. Run migration: ./vendor/bin/neuron db:migrate:run" );
			$this->output->info( "  2. Start dev server: php -S localhost:8000 -t public" );
			$this->output->info( "  3. Visit: http://localhost:8000{$info['routePrefix']}" );
		}
		else
		{
			$this->output->info( "  1. Start dev server: php -S localhost:8000 -t public" );
			$this->output->info( "  2. Visit: http://localhost:8000{$info['routePrefix']}" );
		}
		$this->output->newLine();

		return 0;
	}

	/**
	 * Parse resource name into components
	 */
	private function parseResourceName( string $name ): array
	{
		// Remove "Controller" suffix if present
		$name = preg_replace( '/Controller$/', '', $name );

		// Split by / or \
		$parts = preg_split( '#[/\\\\]+#', trim( $name, '/\\' ) );

		$class = array_pop( $parts );
		$subNamespace = implode( '\\', $parts );

		// Generate various forms
		$model = $class;
		$models = $this->pluralize( $model );
		$variable = lcfirst( $model );
		$variables = lcfirst( $models );
		$tableName = strtolower( $this->underscore( $models ) );

		// Controller path for views (e.g., Admin/Posts)
		$controllerPath = empty( $subNamespace ) ? $models : $subNamespace . '/' . $models;

		// Route prefix (e.g., /admin/posts)
		$routePrefix = '/' . strtolower( str_replace( '\\', '/', $controllerPath ) );

		return [
			'class' => $class . 'Controller',
			'subNamespace' => $subNamespace,
			'model' => $model,
			'models' => $models,
			'variable' => $variable,
			'variables' => $variables,
			'tableName' => $tableName,
			'controllerPath' => $controllerPath,
			'routePrefix' => $routePrefix,
		];
	}

	/**
	 * Generate migration file
	 */
	private function generateMigration( array $info ): bool
	{
		$fields = $this->input->getOption( 'fields', '' );
		$migrationName = 'Create' . $info['models'] . 'Table';

		// Find config directory
		$configPath = $this->findConfigPath();
		if( !$configPath )
		{
			$this->output->warning( 'Config directory not found - skipping migration' );
			return true; // Non-fatal
		}

		// Load MigrationManager
		try
		{
			$basePath = dirname( $configPath );
			$settings = $this->loadSettings( $configPath );
			$manager = new \Neuron\Mvc\Database\MigrationManager( $basePath, $settings );

			// Ensure migrations directory exists
			if( !$manager->ensureMigrationsDirectory() )
			{
				$this->output->error( 'Failed to create migrations directory' );
				return false;
			}

			// Generate migration using custom template
			$template = $this->generateMigrationTemplate( $info, $fields );
			$templatePath = $this->_ProjectPath . '/.scaffold_migration_temp.php';

			// Write temporary template
			$this->fs->writeFile( $templatePath, $template );

			// Execute Phinx create command with our template
			list( $exitCode, $output ) = $manager->execute( 'create', [
				'--environment' => $manager->getEnvironment(),
				'--template' => $templatePath,
				'name' => $migrationName
			] );

			// Clean up temporary template
			@unlink( $templatePath );

			if( $exitCode === 0 )
			{
				// Extract migration file path from Phinx output
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

		// Build field definitions
		$fieldDefinitions = [];
		foreach( $fields as $field )
		{
			$line = "\t\t\$table->addColumn('{$field['name']}', '{$field['type']}'";

			// Add options
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

			// Parse field:type format
			if( !str_contains( $part, ':' ) )
			{
				continue;
			}

			list( $name, $type ) = explode( ':', $part, 2 );
			$name = trim( $name );
			$type = trim( $type );

			// Map type to Phinx type
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

		// String fields get limit
		if( in_array( strtolower( $type ), ['string', 'varchar'] ) )
		{
			$options['limit'] = 255;
		}

		// Boolean fields default to false
		if( in_array( strtolower( $type ), ['boolean', 'bool'] ) )
		{
			$options['default'] = false;
		}

		// Text fields can be null by default
		if( strtolower( $type ) === 'text' )
		{
			$options['null'] = true;
		}

		return $options;
	}

	/**
	 * Generate controller file (reuses logic from ControllerCommand)
	 */
	private function generateController( array $info ): bool
	{
		// Determine full namespace
		$namespace = $info['namespace'];
		if( !empty( $info['subNamespace'] ) )
		{
			$namespace .= '\\' . $info['subNamespace'];
		}

		// Build controller path
		$relativePath = str_replace( '\\', '/', $namespace );
		$relativePath = preg_replace( '#^App/#', 'app/', $relativePath );
		$controllerDir = $this->_ProjectPath . '/' . $relativePath;
		$controllerFile = $controllerDir . '/' . $info['class'] . '.php';

		// Check if exists
		if( $this->fs->fileExists( $controllerFile ) && !$this->input->hasOption( 'force' ) )
		{
			$this->output->error( "Controller already exists: {$controllerFile}" );
			$this->output->info( '   Use --force to overwrite' );
			return false;
		}

		// Load stub
		$stubFile = $info['isApi'] ? 'controller.api.stub' : 'controller.resource.stub';
		if( !$this->templates->exists( $stubFile ) )
		{
			$this->output->error( "Could not load stub file: {$stubFile}" );
			return false;
		}

		// Replace placeholders
		$content = $this->templates->render( $stubFile, array_merge( $info, ['namespace' => $namespace] ) );

		// Create directory
		if( !$this->fs->isDir( $controllerDir ) )
		{
			if( !$this->fs->mkdir( $controllerDir, 0755, true ) )
			{
				$this->output->error( "Could not create directory: {$controllerDir}" );
				return false;
			}
		}

		// Write file
		if( $this->fs->writeFile( $controllerFile, $content ) === false )
		{
			$this->output->error( 'Could not write controller file' );
			return false;
		}

		$this->_Messages[] = "Created controller: {$controllerFile}";
		return true;
	}

	/**
	 * Generate view files
	 */
	private function generateViews( array $info ): bool
	{
		$viewsDir = $this->_ProjectPath . '/resources/views/' . strtolower( $info['models'] );

		// Create views directory
		if( !$this->fs->isDir( $viewsDir ) )
		{
			if( !$this->fs->mkdir( $viewsDir, 0755, true ) )
			{
				$this->output->error( "Could not create views directory: {$viewsDir}" );
				return false;
			}
		}

		// Generate each view
		$views = ['index', 'create', 'edit'];
		foreach( $views as $view )
		{
			$viewFile = $viewsDir . '/' . $view . '.php';

			// Check if exists
			if( $this->fs->fileExists( $viewFile ) && !$this->input->hasOption( 'force' ) )
			{
				$this->output->warning( "View already exists, skipping: {$viewFile}" );
				continue;
			}

			// Load stub
			$stubFile = 'view.' . $view . '.stub';
			if( !$this->templates->exists( $stubFile ) )
			{
				$this->output->error( "Could not load view stub: {$stubFile}" );
				return false;
			}

			// Replace placeholders
			$content = $this->templates->render( $stubFile, $info );

			// Write file
			if( $this->fs->writeFile( $viewFile, $content ) === false )
			{
				$this->output->error( "Could not write view file: {$viewFile}" );
				return false;
			}

			$this->_Messages[] = "Created view: {$viewFile}";
		}

		return true;
	}

	/**
	 * Generate routes in routes.yaml
	 */
	private function generateRoutes( array $info ): bool
	{
		$configDir = $this->_ProjectPath . '/config';
		$routesFile = $configDir . '/routes.yaml';

		// Create config directory if it doesn't exist
		if( !$this->fs->isDir( $configDir ) )
		{
			if( !$this->fs->mkdir( $configDir, 0755, true ) )
			{
				$this->output->error( "Could not create config directory: {$configDir}" );
				return false;
			}
		}

		// Initialize routes file if it doesn't exist
		if( !$this->fs->fileExists( $routesFile ) )
		{
			$initialData = ['routes' => []];
			$yaml = Yaml::dump( $initialData, 2, 2 );
			if( $this->fs->writeFile( $routesFile, $yaml ) === false )
			{
				$this->output->error( "Could not create routes file: {$routesFile}" );
				return false;
			}
		}

		// Load existing routes
		try
		{
			$routesContent = $this->fs->readFile( $routesFile );
			if( $routesContent === false )
			{
				$this->output->error( "Could not read routes file: {$routesFile}" );
				return false;
			}
			$data = Yaml::parse( $routesContent );
		}
		catch( \Exception $e )
		{
			$this->output->error( 'Could not parse routes.yaml: ' . $e->getMessage() );
			return false;
		}

		// Build namespace
		$namespace = $info['namespace'];
		if( !empty( $info['subNamespace'] ) )
		{
			$namespace .= '\\' . $info['subNamespace'];
		}
		$controller = $namespace . '\\' . $info['class'];

		// Generate route name prefix
		$routeNamePrefix = strtolower( str_replace( '/', '_', $info['controllerPath'] ) );

		// Build routes
		$newRoutes = [
			$routeNamePrefix . '_index' => [
				'method' => 'GET',
				'route' => $info['routePrefix'],
				'controller' => $controller . '@index',
			],
			$routeNamePrefix . '_create' => [
				'method' => 'GET',
				'route' => $info['routePrefix'] . '/create',
				'controller' => $controller . '@create',
			],
			$routeNamePrefix . '_store' => [
				'method' => 'POST',
				'route' => $info['routePrefix'],
				'controller' => $controller . '@store',
			],
			$routeNamePrefix . '_edit' => [
				'method' => 'GET',
				'route' => $info['routePrefix'] . '/:id/edit',
				'controller' => $controller . '@edit',
			],
			$routeNamePrefix . '_update' => [
				'method' => 'PUT',
				'route' => $info['routePrefix'] . '/:id',
				'controller' => $controller . '@update',
			],
			$routeNamePrefix . '_destroy' => [
				'method' => 'DELETE',
				'route' => $info['routePrefix'] . '/:id',
				'controller' => $controller . '@destroy',
			],
		];

		// Add show route for API controllers
		if( $info['isApi'] )
		{
			$showRoute = [
				$routeNamePrefix . '_show' => [
					'method' => 'GET',
					'route' => $info['routePrefix'] . '/:id',
					'controller' => $controller . '@show',
				]
			];

			// Insert after index
			$routesArray = [];
			$inserted = false;
			foreach( $newRoutes as $key => $route )
			{
				$routesArray[$key] = $route;
				if( $key === $routeNamePrefix . '_index' && !$inserted )
				{
					$routesArray = array_merge( $routesArray, $showRoute );
					$inserted = true;
				}
			}
			$newRoutes = $routesArray;
		}

		// Add filter if specified
		if( !empty( $info['filter'] ) )
		{
			foreach( $newRoutes as &$route )
			{
				$route['filter'] = $info['filter'];
			}
		}

		// Add routes to existing data
		if( !isset( $data['routes'] ) || !is_array( $data['routes'] ) )
		{
			$data['routes'] = [];
		}

		$data['routes'] = array_merge( $data['routes'], $newRoutes );

		// Write back to file
		try
		{
			$yaml = Yaml::dump( $data, 10, 2 );
			if( $this->fs->writeFile( $routesFile, $yaml ) === false )
			{
				$this->output->error( 'Could not write routes file' );
				return false;
			}
		}
		catch( \Exception $e )
		{
			$this->output->error( 'Could not write routes.yaml: ' . $e->getMessage() );
			return false;
		}

		$routeCount = count( $newRoutes );
		$this->_Messages[] = "Added {$routeCount} routes to {$routesFile}";
		return true;
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
