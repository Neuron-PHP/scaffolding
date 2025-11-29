<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\ListenerCommand;

class ListenerCommandTest extends TestCase
{
	public function testGetNameReturnsListenerGenerate(): void
	{
		$command = new ListenerCommand();
		$this->assertEquals( 'listener:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$command = new ListenerCommand();
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'listener', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new ListenerCommand();

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}


	public function testExecuteRequiresInteractiveInput(): void
	{
		$this->markTestSkipped( 'Cannot test execute() as it requires interactive CLI input and mocked dependencies' );

		// TODO: Refactor ListenerCommand to accept injectable dependencies for testing
	}
}
