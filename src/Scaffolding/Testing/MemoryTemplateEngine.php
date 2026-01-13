<?php

namespace Neuron\Scaffolding\Testing;

use Neuron\Scaffolding\Contracts\ITemplateEngine;

/**
 * In-memory template engine for testing
 */
class MemoryTemplateEngine implements ITemplateEngine
{
	private array $templates = [];

	/**
	 * Add a template to memory
	 *
	 * @param string $name Template name
	 * @param string $content Template content
	 * @return self
	 */
	public function addTemplate( string $name, string $content ): self
	{
		$this->templates[$name] = $content;
		return $this;
	}

	/**
	 * @inheritDoc
	 */
	public function exists( string $template ): bool
	{
		return isset( $this->templates[$template] );
	}

	/**
	 * @inheritDoc
	 */
	public function load( string $template ): ?string
	{
		return $this->templates[$template] ?? null;
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
