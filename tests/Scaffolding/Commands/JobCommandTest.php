<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\JobCommand;

class JobCommandTest extends TestCase
{
	public function testGetNameReturnsJobGenerate(): void
	{
		$command = new JobCommand();
		$this->assertEquals( 'job:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$command = new JobCommand();
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'job', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new JobCommand();

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}


	public function testExecuteRequiresInteractiveInput(): void
	{
		$this->markTestSkipped( 'Cannot test execute() as it requires interactive CLI input and mocked dependencies' );

		// TODO: Refactor JobCommand to accept injectable dependencies for testing
	}
}
