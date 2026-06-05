<?php

namespace Tests\Scaffolding\Services;

use PHPUnit\Framework\TestCase;
use Neuron\Core\System\MemoryFileSystem;
use Neuron\Core\System\RealFileSystem;
use Neuron\Scaffolding\Schema\Field;
use Neuron\Scaffolding\Schema\FieldSet;
use Neuron\Scaffolding\Services\FileTemplateEngine;
use Neuron\Scaffolding\Services\ResourceScaffolder;

class ResourceScaffolderTest extends TestCase
{
	private MemoryFileSystem $fs;
	private ResourceScaffolder $scaffolder;

	protected function setUp(): void
	{
		$this->fs = new MemoryFileSystem();
		$this->fs->setCwd( '/app' );

		$stubPath = __DIR__ . '/../../../src/Scaffolding/Stubs';
		$templates = new FileTemplateEngine( new RealFileSystem(), $stubPath );

		$this->scaffolder = new ResourceScaffolder( $this->fs, $templates, '/app' );
	}

	private function info(): array
	{
		return [
			'class'            => 'PostController',
			'subNamespace'     => '',
			'model'            => 'Post',
			'models'           => 'Posts',
			'variable'         => 'post',
			'variables'        => 'posts',
			'tableName'        => 'posts',
			'controllerPath'   => 'Posts',
			'routePrefix'      => '/posts',
			'namespace'        => 'App\\Controllers',
			'modelNamespace'   => 'App\\Models',
			'repoNamespace'    => 'App\\Repositories',
			'repoInterface'    => 'IPostRepository',
			'repoClass'        => 'DatabasePostRepository',
			'viewPath'         => 'posts',
			'routeName'        => 'posts',
			'isApi'            => false,
			'primaryKey'       => 'id',
			'primaryKeyGetter' => 'getId',
		];
	}

	private function fields(): FieldSet
	{
		return new FieldSet( [
			new Field( 'id', 'integer', isPrimary: true, autoIncrement: true ),
			new Field( 'title', 'string', length: 255 ),
			new Field( 'body', 'text', nullable: true ),
			new Field( 'published', 'boolean' ),
			new Field( 'created_at', 'datetime', nullable: true ),
			new Field( 'updated_at', 'datetime', nullable: true ),
		] );
	}

	public function testGenerateModel(): void
	{
		$file = $this->scaffolder->generateModel( $this->info(), $this->fields() );
		$this->assertEquals( '/app/app/Models/Post.php', $file );

		$content = $this->fs->getFiles()[ $file ];
		$this->assertStringNotContainsString( '{{', $content );
		$this->assertStringContainsString( "#[Table('posts', primaryKey: 'id')]", $content );
		$this->assertStringContainsString( 'private ?int $_id = null;', $content );
		$this->assertStringContainsString( "private string \$_title = '';", $content );
		$this->assertStringContainsString( 'public function getTitle(): string', $content );
		$this->assertStringContainsString( 'public static function fromArray( array $data ): static', $content );
		$this->assertStringContainsString( 'public function toArray(): array', $content );
	}

	public function testGenerateDto(): void
	{
		$file = $this->scaffolder->generateDto( $this->info(), $this->fields() );
		$this->assertEquals( '/app/resources/dtos/posts.yaml', $file );

		$content = $this->fs->getFiles()[ $file ];
		$this->assertStringNotContainsString( '{{', $content );
		$this->assertStringContainsString( 'dto:', $content );
		$this->assertStringContainsString( '  title:', $content );
		$this->assertStringContainsString( 'type: string', $content );
		$this->assertStringContainsString( 'type: boolean', $content );
		// Primary key and timestamps must be excluded from the DTO.
		$this->assertStringNotContainsString( "id:\n", $content );
		$this->assertStringNotContainsString( 'created_at:', $content );
	}

	public function testGenerateRepository(): void
	{
		$files = $this->scaffolder->generateRepository( $this->info() );
		$this->assertCount( 2, $files );

		$interface = $this->fs->getFiles()[ '/app/app/Repositories/IPostRepository.php' ];
		$this->assertStringContainsString( 'interface IPostRepository', $interface );
		$this->assertStringContainsString( 'public function findById( int $id ): ?Post;', $interface );

		$class = $this->fs->getFiles()[ '/app/app/Repositories/DatabasePostRepository.php' ];
		$this->assertStringNotContainsString( '{{', $class );
		$this->assertStringContainsString( 'class DatabasePostRepository implements IPostRepository', $class );
		$this->assertStringContainsString( 'Post::query()->where( \'id\', $id )->first()', $class );
		$this->assertStringContainsString( 'Post::setPdo(', $class );
	}

	public function testGenerateController(): void
	{
		$file = $this->scaffolder->generateController( $this->info() );
		$this->assertEquals( '/app/app/Controllers/PostController.php', $file );

		$content = $this->fs->getFiles()[ $file ];
		$this->assertStringNotContainsString( '{{', $content );
		$this->assertStringContainsString( 'class PostController extends Base', $content );
		$this->assertStringContainsString( "#[\\Neuron\\Routing\\Attributes\\Get( '/posts', name: 'posts_index' )]", $content );
		$this->assertStringContainsString( "filters: ['csrf']", $content );
		$this->assertStringContainsString( 'public function index( Request $request ): string', $content );
		$this->assertStringContainsString( 'resources/dtos/posts.yaml', $content );
	}

	public function testGenerateViews(): void
	{
		$files = $this->scaffolder->generateViews( $this->info(), $this->fields() );
		$this->assertCount( 5, $files );

		$created = $this->fs->getFiles();

		$this->assertArrayHasKey( '/app/resources/views/posts/index.php', $created );
		$this->assertArrayHasKey( '/app/resources/views/posts/create.php', $created );
		$this->assertArrayHasKey( '/app/resources/views/posts/edit.php', $created );
		$this->assertArrayHasKey( '/app/resources/views/posts/show.php', $created );
		$this->assertArrayHasKey( '/app/resources/views/posts/_form.php', $created );

		$index = $created['/app/resources/views/posts/index.php'];
		$this->assertStringNotContainsString( '{{', $index );
		$this->assertStringContainsString( 'foreach( $posts as $post )', $index );
		$this->assertStringContainsString( '$post->getId()', $index );

		$form = $created['/app/resources/views/posts/_form.php'];
		$this->assertStringContainsString( 'csrf_field()', $form );
		$this->assertStringContainsString( 'name="title"', $form );
	}

	public function testGuardsAgainstOverwriteWithoutForce(): void
	{
		$this->fs->addDirectory( '/app/app/Models' );
		$this->fs->addFile( '/app/app/Models/Post.php', 'existing' );

		$this->expectException( \RuntimeException::class );
		$this->scaffolder->generateModel( $this->info(), $this->fields() );
	}
}
