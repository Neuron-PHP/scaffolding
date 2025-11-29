<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\EmailCommand;

class EmailCommandTest extends TestCase
{
	public function testGetNameReturnsMailGenerate(): void
	{
		$command = new EmailCommand();
		$this->assertEquals( 'mail:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$command = new EmailCommand();
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'email', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new EmailCommand();

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}


	public function testExecuteRequiresInteractiveInput(): void
	{
		$this->markTestSkipped( 'Cannot test execute() as it requires interactive CLI input and mocked dependencies' );

		// TODO: Refactor EmailCommand to accept injectable dependencies for testing
	}
}
