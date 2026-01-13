<?php

namespace Tests\Scaffolding;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Provider;
use Neuron\Cli\Commands\Registry;

class ProviderTest extends TestCase
{
	public function testRegisterRegistersControllerCommand(): void
	{
		$registry = $this->createMock( Registry::class );

		$registry->expects( $this->exactly( 8 ) )
			->method( 'register' )
			->withConsecutive(
				['controller:generate', 'Neuron\\Scaffolding\\Commands\\ControllerCommand'],
				['event:generate', 'Neuron\\Scaffolding\\Commands\\EventCommand'],
				['listener:generate', 'Neuron\\Scaffolding\\Commands\\ListenerCommand'],
				['job:generate', 'Neuron\\Scaffolding\\Commands\\JobCommand'],
				['initializer:generate', 'Neuron\\Scaffolding\\Commands\\InitializerCommand'],
				['mail:generate', 'Neuron\\Scaffolding\\Commands\\EmailCommand'],
				['queue:install', 'Neuron\\Scaffolding\\Commands\\Queue\\InstallCommand'],
				['scaffold:generate', 'Neuron\\Scaffolding\\Commands\\ScaffoldCommand']
			);

		Provider::register( $registry );
	}

	public function testRegisterIsStaticMethod(): void
	{
		$reflection = new \ReflectionClass( Provider::class );
		$method = $reflection->getMethod( 'register' );

		$this->assertTrue( $method->isStatic() );
		$this->assertTrue( $method->isPublic() );
	}

	public function testRegisterAcceptsRegistryParameter(): void
	{
		$reflection = new \ReflectionClass( Provider::class );
		$method = $reflection->getMethod( 'register' );
		$parameters = $method->getParameters();

		$this->assertCount( 1, $parameters );
		$this->assertEquals( 'registry', $parameters[0]->getName() );
		$this->assertEquals( Registry::class, $parameters[0]->getType()->getName() );
	}

	public function testRegisterReturnsVoid(): void
	{
		$reflection = new \ReflectionClass( Provider::class );
		$method = $reflection->getMethod( 'register' );
		$returnType = $method->getReturnType();

		$this->assertNotNull( $returnType );
		$this->assertEquals( 'void', $returnType->getName() );
	}

	public function testProviderRegistersAllEightCommands(): void
	{
		$registry = $this->createMock( Registry::class );

		// Count how many times register is called
		$registry->expects( $this->exactly( 8 ) )
			->method( 'register' );

		Provider::register( $registry );
	}

	public function testProviderRegistersCommandsWithCorrectKeys(): void
	{
		$expectedCommands = [
			'controller:generate',
			'event:generate',
			'listener:generate',
			'job:generate',
			'initializer:generate',
			'mail:generate',
			'queue:install',
			'scaffold:generate',
		];

		$registry = $this->createMock( Registry::class );

		$actualCommands = [];
		$registry->method( 'register' )
			->willReturnCallback( function( $name, $class ) use ( &$actualCommands ) {
				$actualCommands[] = $name;
			} );

		Provider::register( $registry );

		$this->assertEquals( $expectedCommands, $actualCommands );
	}

	public function testProviderRegistersCommandsWithCorrectClasses(): void
	{
		$expectedClasses = [
			'Neuron\\Scaffolding\\Commands\\ControllerCommand',
			'Neuron\\Scaffolding\\Commands\\EventCommand',
			'Neuron\\Scaffolding\\Commands\\ListenerCommand',
			'Neuron\\Scaffolding\\Commands\\JobCommand',
			'Neuron\\Scaffolding\\Commands\\InitializerCommand',
			'Neuron\\Scaffolding\\Commands\\EmailCommand',
			'Neuron\\Scaffolding\\Commands\\Queue\\InstallCommand',
			'Neuron\\Scaffolding\\Commands\\ScaffoldCommand',
		];

		$registry = $this->createMock( Registry::class );

		$actualClasses = [];
		$registry->method( 'register' )
			->willReturnCallback( function( $name, $class ) use ( &$actualClasses ) {
				$actualClasses[] = $class;
			} );

		Provider::register( $registry );

		$this->assertEquals( $expectedClasses, $actualClasses );
	}

	public function testAllRegisteredCommandClassesExist(): void
	{
		$registry = $this->createMock( Registry::class );

		$registry->method( 'register' )
			->willReturnCallback( function( $name, $class ) {
				$this->assertTrue(
					class_exists( $class ),
					"Command class does not exist: $class"
				);
			} );

		Provider::register( $registry );
	}

	public function testProviderClassHasCorrectNamespace(): void
	{
		$reflection = new \ReflectionClass( Provider::class );
		$this->assertEquals( 'Neuron\\Scaffolding', $reflection->getNamespaceName() );
	}

	public function testProviderClassHasOnlyRegisterMethod(): void
	{
		$reflection = new \ReflectionClass( Provider::class );
		$methods = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );

		// Should only have one public method: register()
		$this->assertCount( 1, $methods );
		$this->assertEquals( 'register', $methods[0]->getName() );
	}
}
