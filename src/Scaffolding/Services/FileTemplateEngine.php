<?php

namespace Neuron\Scaffolding\Services;

use Neuron\Core\System\IFileSystem;
use Neuron\Scaffolding\Contracts\ITemplateEngine;

/**
 * File-based template engine for loading stub files
 */
class FileTemplateEngine implements ITemplateEngine
{
	/**
	 * @param IFileSystem $fs Filesystem implementation
	 * @param string $stubPath Base path to stub files
	 */
	public function __construct(
		private IFileSystem $fs,
		private string $stubPath
	) {}

	/**
	 * @inheritDoc
	 */
	public function exists( string $template ): bool
	{
		$path = $this->stubPath . '/' . $template;
		return $this->fs->fileExists( $path );
	}

	/**
	 * @inheritDoc
	 */
	public function load( string $template ): ?string
	{
		$path = $this->stubPath . '/' . $template;

		if( !$this->exists( $template ) )
		{
			return null;
		}

		$content = $this->fs->readFile( $path );
		return $content === false ? null : $content;
	}

	/**
	 * @inheritDoc
	 */
	public function render( string $template, array $data ): string
	{
		$content = $this->load( $template );

		if( $content === null )
		{
			throw new \Exception( "Template not found: {$template}" );
		}

		foreach( $data as $key => $value )
		{
			$content = str_replace( '{{' . $key . '}}', $value ?? '', $content );
		}

		return $content;
	}
}
