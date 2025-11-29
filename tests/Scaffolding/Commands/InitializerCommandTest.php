<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\InitializerCommand;

class InitializerCommandTest extends TestCase
{
	public function testGetNameReturnsInitializerGenerate(): void
	{
		$command = new InitializerCommand();
		$this->assertEquals( 'initializer:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$command = new InitializerCommand();
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'initializer', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new InitializerCommand();

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}


	public function testExecuteRequiresInteractiveInput(): void
	{
		$this->markTestSkipped( 'Cannot test execute() as it requires interactive CLI input and mocked dependencies' );

		// TODO: Refactor InitializerCommand to accept injectable dependencies for testing
	}
}
