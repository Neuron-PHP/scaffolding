<?php

namespace Neuron\Scaffolding\Commands;

use Neuron\Cli\Commands\Command;
use Neuron\Core\System\IFileSystem;
use Neuron\Core\System\RealFileSystem;
use Neuron\Scaffolding\Contracts\ITemplateEngine;
use Neuron\Scaffolding\Services\FileTemplateEngine;

/**
 * Generate email template files
 */
class EmailCommand extends Command
{
	private string $_projectPath;
	private IFileSystem $fs;
	private ITemplateEngine $templates;

	public function __construct( ?IFileSystem $fs = null, ?ITemplateEngine $templates = null )
	{
		$this->fs = $fs ?? new RealFileSystem();
		$this->_projectPath = $this->fs->getcwd();

		// Default to FileTemplateEngine if not provided
		if( $templates === null )
		{
			$componentPath = dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) );
			$stubPath = $componentPath . '/src/Cms/Cli/Commands/Generate/stubs';
			$templates = new FileTemplateEngine( $this->fs, $stubPath );
		}
		$this->templates = $templates;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'mail:generate';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Generate a new email template';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		// No additional configuration needed
	}

	/**
	 * Execute the command
	 */
	public function execute( array $parameters = [] ): int
	{
		// Get template name from first parameter
		$templateName = $parameters[0] ?? null;

		if( !$templateName )
		{
			$this->output->error( "Please provide a template name" );
			$this->output->info( "Usage: php neuron mail:generate <name>" );
			$this->output->info( "Example: php neuron mail:generate welcome" );
			return 1;
		}

		// Validate template name (should be lowercase with hyphens)
		if( !preg_match( '/^[a-z][a-z0-9-]*$/', $templateName ) )
		{
			$this->output->error( "Template name must be lowercase and contain only letters, numbers, and hyphens" );
			$this->output->error( "Example: welcome, password-reset, order-confirmation" );
			return 1;
		}

		// Create the template file
		if( !$this->createTemplate( $templateName ) )
		{
			return 1;
		}

		$this->output->success( "Email template created successfully!" );
		$this->output->info( "Template: resources/views/emails/{$templateName}.php" );
		$this->output->info( "" );
		$this->output->info( "Usage in code:" );
		$this->output->info( "  email()->to('user@example.com')" );
		$this->output->info( "         ->subject('Welcome!')" );
		$this->output->info( "         ->template('emails/{$templateName}', \$data)" );
		$this->output->info( "         ->send();" );

		return 0;
	}

	/**
	 * Create the template file
	 */
	private function createTemplate( string $name ): bool
	{
		$emailsDir = $this->_projectPath . '/resources/views/emails';

		// Create emails directory if it doesn't exist
		if( !$this->fs->isDir( $emailsDir ) )
		{
			if( !$this->fs->mkdir( $emailsDir, 0755, true ) )
			{
				$this->output->error( "Failed to create emails directory" );
				return false;
			}
		}

		$filePath = $emailsDir . '/' . $name . '.php';

		// Check if file already exists
		if( $this->fs->fileExists( $filePath ) )
		{
			$this->output->error( "Template already exists: resources/views/emails/{$name}.php" );
			return false;
		}

		// Check if stub template exists
		if( !$this->templates->exists( 'email.stub' ) )
		{
			$this->output->error( "Stub template not found: email.stub" );
			return false;
		}

		// Create title from name (e.g., "welcome" -> "Welcome", "password-reset" -> "Password Reset")
		$title = ucwords( str_replace( '-', ' ', $name ) );

		// Render template with data
		try
		{
			$content = $this->templates->render( 'email.stub', [
				'title' => $title,
				'content' => '<p>Your email content goes here.</p>'
			] );
		}
		catch( \Exception $e )
		{
			$this->output->error( "Failed to render template: " . $e->getMessage() );
			return false;
		}

		// Write the file
		if( $this->fs->writeFile( $filePath, $content ) === false )
		{
			$this->output->error( "Failed to create template file" );
			return false;
		}

		return true;
	}
}
