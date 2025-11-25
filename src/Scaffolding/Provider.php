<?php

namespace Neuron\Scaffolding;

use Neuron\Cli\Commands\Registry;

/**
 * CLI provider for the Scaffolding component.
 * Registers all code generation and scaffolding commands.
 */
class Provider
{
	/**
	 * Register scaffolding commands with the CLI registry
	 *
	 * @param Registry $registry CLI Registry instance
	 * @return void
	 */
	public static function register( Registry $registry ): void
	{
		// Controller generator
		$registry->register(
			'controller:generate',
			'Neuron\\Scaffolding\\Commands\\ControllerCommand'
		);

		// Event & Listener generators
		$registry->register(
			'event:generate',
			'Neuron\\Scaffolding\\Commands\\EventCommand'
		);

		$registry->register(
			'listener:generate',
			'Neuron\\Scaffolding\\Commands\\ListenerCommand'
		);

		// Job generator
		$registry->register(
			'job:generate',
			'Neuron\\Scaffolding\\Commands\\JobCommand'
		);

		// Initializer generator
		$registry->register(
			'initializer:generate',
			'Neuron\\Scaffolding\\Commands\\InitializerCommand'
		);

		// Email template generator
		$registry->register(
			'mail:generate',
			'Neuron\\Scaffolding\\Commands\\EmailCommand'
		);

		// Queue installation
		$registry->register(
			'queue:install',
			'Neuron\\Scaffolding\\Commands\\Queue\\InstallCommand'
		);
	}
}
