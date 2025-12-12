<?php

namespace Neuron\Scaffolding\Commands;

use Neuron\Cli\Commands\Command;
use Neuron\Core\System\IFileSystem;
use Neuron\Core\System\RealFileSystem;
use Neuron\Scaffolding\Contracts\ITemplateEngine;
use Neuron\Scaffolding\Services\FileTemplateEngine;

/**
 * CLI command for generating initializer classes.
 */
class InitializerCommand extends Command
{
	private string $_ProjectPath;
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
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'initializer:generate';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Generate an initializer class';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		$this->addArgument( 'name', true, 'Initializer name (e.g., QueueInitializer)' );
		$this->addOption( 'namespace', null, true, 'Initializer namespace', 'App\\Initializers' );
		$this->addOption( 'force', 'f', false, 'Overwrite existing files' );
	}

	/**
	 * Execute the command
	 */
	public function execute(): int
	{
		$this->output->info( "\n╔═══════════════════════════════════════╗" );
		$this->output->info( "║  Initializer Generator                ║" );
		$this->output->info( "╚═══════════════════════════════════════╝\n" );

		// Get initializer name
		$initializerName = $this->input->getArgument( 'name' );
		if( empty( $initializerName ) )
		{
			$this->output->error( 'Initializer name is required' );
			return 1;
		}

		// Get namespace
		$namespace = $this->input->getOption( 'namespace', 'App\\Initializers' );
		$namespace = rtrim( $namespace, '\\' );

		// Generate initializer
		if( !$this->generateInitializer( $initializerName, $namespace ) )
		{
			return 1;
		}

		$this->output->newLine();
		$this->output->success( 'Initializer generated successfully!' );
		$this->output->info( "\nThe initializer will be automatically loaded by InitializerRunner on application start." );
		$this->output->info( "Implement your initialization logic in the run() method." );

		return 0;
	}

	/**
	 * Generate initializer class
	 */
	private function generateInitializer( string $initializerName, string $namespace ): bool
	{
		// Determine file path
		$namespacePath = str_replace( '\\', '/', str_replace( 'App\\', '', $namespace ) );
		$initializerDir = $this->_ProjectPath . '/app/' . $namespacePath;
		$initializerFile = $initializerDir . '/' . $initializerName . '.php';

		// Check if exists
		if( $this->fs->fileExists( $initializerFile ) && !$this->input->hasOption( 'force' ) )
		{
			$this->output->error( "Initializer already exists: {$initializerFile}" );
			$this->output->info( 'Use --force to overwrite' );
			return false;
		}

		// Create directory
		if( !$this->fs->isDir( $initializerDir ) )
		{
			if( !$this->fs->mkdir( $initializerDir, 0755, true ) )
			{
				$this->output->error( "Could not create directory: {$initializerDir}" );
				return false;
			}
		}

		// Load stub and replace placeholders
		if( !$this->templates->exists( 'initializer.stub' ) )
		{
			$this->output->error( 'Could not load initializer stub' );
			return false;
		}

		// Render template
		$content = $this->templates->render( 'initializer.stub', [
			'namespace' => $namespace,
			'class' => $initializerName
		] );

		// Write file
		if( $this->fs->writeFile( $initializerFile, $content ) === false )
		{
			$this->output->error( 'Could not write initializer file' );
			return false;
		}

		$this->output->success( "Created initializer: {$initializerFile}" );
		return true;
	}
}
