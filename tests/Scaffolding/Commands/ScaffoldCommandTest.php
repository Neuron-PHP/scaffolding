<?php

namespace Tests\Scaffolding\Commands;

use PHPUnit\Framework\TestCase;
use Neuron\Scaffolding\Commands\ScaffoldCommand;

class ScaffoldCommandTest extends TestCase
{
	public function testGetNameReturnsScaffoldGenerate(): void
	{
		$command = new ScaffoldCommand();
		$this->assertEquals( 'scaffold:generate', $command->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$command = new ScaffoldCommand();
		$description = $command->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertStringContainsString( 'scaffold', strtolower( $description ) );
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new ScaffoldCommand();

		// Call configure method
		$command->configure();

		// If no exception thrown, configuration succeeded
		$this->assertTrue( true );
	}

	public function testParseResourceNameSimpleName(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseResourceName' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'Post' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Post', $result['model'] );
		$this->assertEquals( 'Posts', $result['models'] );
		$this->assertEquals( 'post', $result['variable'] );
		$this->assertEquals( 'posts', $result['variables'] );
		$this->assertEquals( 'posts', $result['tableName'] );
	}

	public function testParseResourceNameWithNamespace(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseResourceName' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'Admin/Post' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Admin', $result['subNamespace'] );
		$this->assertEquals( 'Post', $result['model'] );
		$this->assertEquals( 'Posts', $result['models'] );
		$this->assertEquals( 'posts', $result['tableName'] );
	}

	public function testParseResourceNameRemovesControllerSuffix(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseResourceName' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'PostController' );

		$this->assertEquals( 'PostController', $result['class'] );
		$this->assertEquals( 'Post', $result['model'] );
	}

	public function testParseFieldsEmptyString(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseFields' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, '' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function testParseFieldsSimpleTypes(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseFields' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'title:string,age:integer,active:boolean' );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );

		// Check first field
		$this->assertEquals( 'title', $result[0]['name'] );
		$this->assertEquals( 'string', $result[0]['type'] );

		// Check second field
		$this->assertEquals( 'age', $result[1]['name'] );
		$this->assertEquals( 'integer', $result[1]['type'] );

		// Check third field
		$this->assertEquals( 'active', $result[2]['name'] );
		$this->assertEquals( 'boolean', $result[2]['type'] );
	}

	public function testParseFieldsComplexString(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'parseFields' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'name:string, email:string, bio:text, created_at:datetime' );

		$this->assertIsArray( $result );
		$this->assertCount( 4, $result );

		$this->assertEquals( 'name', $result[0]['name'] );
		$this->assertEquals( 'string', $result[0]['type'] );

		$this->assertEquals( 'email', $result[1]['name'] );
		$this->assertEquals( 'string', $result[1]['type'] );

		$this->assertEquals( 'bio', $result[2]['name'] );
		$this->assertEquals( 'text', $result[2]['type'] );

		$this->assertEquals( 'created_at', $result[3]['name'] );
		$this->assertEquals( 'datetime', $result[3]['type'] );
	}

	public function testMapFieldTypeString(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'mapFieldType' );
		$method->setAccessible( true );

		$this->assertEquals( 'string', $method->invoke( $command, 'string' ) );
		$this->assertEquals( 'string', $method->invoke( $command, 'varchar' ) );
	}

	public function testMapFieldTypeInteger(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'mapFieldType' );
		$method->setAccessible( true );

		$this->assertEquals( 'integer', $method->invoke( $command, 'integer' ) );
		$this->assertEquals( 'integer', $method->invoke( $command, 'int' ) );
	}

	public function testMapFieldTypeBoolean(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'mapFieldType' );
		$method->setAccessible( true );

		$this->assertEquals( 'boolean', $method->invoke( $command, 'boolean' ) );
		$this->assertEquals( 'boolean', $method->invoke( $command, 'bool' ) );
	}

	public function testMapFieldTypeText(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'mapFieldType' );
		$method->setAccessible( true );

		$this->assertEquals( 'text', $method->invoke( $command, 'text' ) );
	}

	public function testMapFieldTypeDatetime(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'mapFieldType' );
		$method->setAccessible( true );

		$this->assertEquals( 'datetime', $method->invoke( $command, 'datetime' ) );
		$this->assertEquals( 'datetime', $method->invoke( $command, 'timestamp' ) );
	}

	public function testMapFieldTypeUnknown(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'mapFieldType' );
		$method->setAccessible( true );

		// Unknown types default to string
		$this->assertEquals( 'string', $method->invoke( $command, 'unknown_type' ) );
	}

	public function testPluralizeCommonWords(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'pluralize' );
		$method->setAccessible( true );

		$this->assertEquals( 'Posts', $method->invoke( $command, 'Post' ) );
		$this->assertEquals( 'Users', $method->invoke( $command, 'User' ) );
		$this->assertEquals( 'Categories', $method->invoke( $command, 'Category' ) );
		$this->assertEquals( 'Classes', $method->invoke( $command, 'Class' ) );
		$this->assertEquals( 'Boxes', $method->invoke( $command, 'Box' ) );
	}

	public function testUnderscoreConversion(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'underscore' );
		$method->setAccessible( true );

		$this->assertEquals( 'posts', $method->invoke( $command, 'Posts' ) );
		$this->assertEquals( 'blog_posts', $method->invoke( $command, 'BlogPosts' ) );
		$this->assertEquals( 'user_profiles', $method->invoke( $command, 'UserProfiles' ) );
	}

	public function testGetFieldOptionsForString(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'getFieldOptions' );
		$method->setAccessible( true );

		$options = $method->invoke( $command, 'string', 'name' );

		$this->assertIsArray( $options );
		$this->assertArrayHasKey( 'limit', $options );
		$this->assertEquals( 255, $options['limit'] );
	}

	public function testGetFieldOptionsForBoolean(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'getFieldOptions' );
		$method->setAccessible( true );

		$options = $method->invoke( $command, 'boolean', 'active' );

		$this->assertIsArray( $options );
		$this->assertArrayHasKey( 'default', $options );
		$this->assertFalse( $options['default'] );
	}

	public function testGetFieldOptionsForText(): void
	{
		$command = new ScaffoldCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'getFieldOptions' );
		$method->setAccessible( true );

		$options = $method->invoke( $command, 'text', 'description' );

		$this->assertIsArray( $options );
		$this->assertArrayHasKey( 'null', $options );
		$this->assertTrue( $options['null'] );
	}

	public function testLoadStubReturnsNullWhenFileDoesNotExist(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ScaffoldCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'loadStub' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'nonexistent.stub' );

		$this->assertNull( $result );
	}

	public function testLoadStubReturnsContentWhenFileExists(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();
		$fs->setCwd( '/test-project' );

		$command = new ScaffoldCommand( $fs );

		// Get the actual stub path from the command
		$reflection = new \ReflectionClass( $command );
		$stubPathProperty = $reflection->getProperty( '_StubPath' );
		$stubPathProperty->setAccessible( true );
		$stubBasePath = $stubPathProperty->getValue( $command );

		// Set up the stub file
		$stubPath = $stubBasePath . '/controller.stub';
		$stubContent = '<?php stub content';
		$fs->addFile( $stubPath, $stubContent );


		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'loadStub' );
		$method->setAccessible( true );

		$result = $method->invoke( $command, 'controller.stub' );

		$this->assertEquals( $stubContent, $result );
	}

	public function testReplacePlaceholdersSubstitutesVariables(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ScaffoldCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'replacePlaceholders' );
		$method->setAccessible( true );

		$content = 'Hello {{name}}, welcome to {{place}}!';
		$replacements = [
			'name' => 'John',
			'place' => 'Neuron'
		];

		$result = $method->invoke( $command, $content, $replacements );

		$this->assertEquals( 'Hello John, welcome to Neuron!', $result );
	}

	public function testReplacePlaceholdersHandlesMultipleOccurrences(): void
	{
		$fs = new \Neuron\Core\System\MemoryFileSystem();
		$fs->setCwd( '/test-project' );
		$command = new ScaffoldCommand( $fs );

		$reflection = new \ReflectionClass( $command );
		$method = $reflection->getMethod( 'replacePlaceholders' );
		$method->setAccessible( true );

		$content = '{{var}} and {{var}} are the same as {{var}}';
		$replacements = ['var' => 'test'];

		$result = $method->invoke( $command, $content, $replacements );

		$this->assertEquals( 'test and test are the same as test', $result );
	}

	public function testExecuteRequiresInteractiveInput(): void
	{
		$this->markTestSkipped( 'Cannot test execute() as it requires interactive CLI input and mocked dependencies' );

		// TODO: Refactor ScaffoldCommand to accept injectable dependencies for testing
		// This would allow testing without actual filesystem operations
	}
}
