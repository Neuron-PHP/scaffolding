<?php

namespace Neuron\Scaffolding\Contracts;

/**
 * Template engine for loading and rendering stub files
 */
interface ITemplateEngine
{
	/**
	 * Check if a template exists
	 *
	 * @param string $template Template name (e.g., 'controller.resource.stub')
	 * @return bool
	 */
	public function exists( string $template ): bool;

	/**
	 * Load raw template content
	 *
	 * @param string $template Template name
	 * @return string|null Template content or null if not found
	 */
	public function load( string $template ): ?string;

	/**
	 * Render template with data replacements
	 *
	 * @param string $template Template name
	 * @param array $data Key-value pairs for {{placeholder}} replacement
	 * @return string Rendered content
	 * @throws \Exception if template not found
	 */
	public function render( string $template, array $data ): string;
}
