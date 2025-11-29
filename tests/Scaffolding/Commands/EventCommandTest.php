<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\EventCommand;

class EventCommandTest extends TestCase
{
	public function testGetNameReturnsEventGenerate(): void
	{
		$command = new EventCommand();
		$this->assertEquals( 'event:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$command = new EventCommand();
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new EventCommand();

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}


	public function testExecuteRequiresInteractiveInput(): void
	{
		$this->markTestSkipped( 'Cannot test execute() as it requires interactive CLI input and mocked dependencies' );

		// TODO: Refactor EventCommand to accept injectable dependencies for testing
	}
}
